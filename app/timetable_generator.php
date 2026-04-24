<?php
require_once __DIR__ . '/db.php';

class TimetableGenerator {
    public const MODE_REQUIRED_ONLY = 'required_only';
    public const MODE_FILL_ALL = 'fill_all';

    private static function isLabCourse(string $courseCode, string $courseTitle): bool {
        $text = strtolower(trim($courseCode . ' ' . $courseTitle));
        return str_contains($text, 'lab')
            || str_contains($text, 'laboratory')
            || str_contains($text, 'practical');
    }

    private static function selectRoomCandidates(
        int $preferredRoomId,
        bool $requiresLab,
        array $allRoomIds,
        array $allLabRoomIds,
        array $isLabByRoomId
    ): array {
        if ($requiresLab) {
            if ($preferredRoomId > 0 && !empty($isLabByRoomId[$preferredRoomId])) {
                return [$preferredRoomId];
            }
            return $allLabRoomIds;
        }

        if ($preferredRoomId > 0) {
            return [$preferredRoomId];
        }
        return $allRoomIds;
    }

    private static function facultyHasAdjacentSlot(PDO $pdo, int $facultyId, int $day, string $start, string $end): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM timetable_slots '
            . 'WHERE faculty_id=? AND day_of_week=? '
            . 'AND (end_time=? OR start_time=?) '
            . 'LIMIT 1'
        );
        $stmt->execute([$facultyId, $day, $start, $end]);
        return (bool)$stmt->fetchColumn();
    }

    private static function availableRoomIds(PDO $pdo, array $roomCandidates, int $day, string $start, string $end): array {
        if (!$roomCandidates) return [];
        $in = implode(',', array_fill(0, count($roomCandidates), '?'));
        $sql = 'SELECT r.room_id '
            . 'FROM rooms r '
            . 'WHERE r.room_id IN (' . $in . ') '
            . 'AND r.active=1 '
            . 'AND NOT EXISTS ('
            . '  SELECT 1 FROM timetable_slots t '
            . '  WHERE t.room_id=r.room_id AND t.day_of_week=? '
            . '    AND (? < t.end_time AND ? > t.start_time)'
            . ') '
            . 'ORDER BY r.room_code';
        $stmt = $pdo->prepare($sql);
        $params = array_merge($roomCandidates, [(int)$day, $start, $end]);
        $stmt->execute($params);
        return array_map(fn($r) => (int)$r['room_id'], $stmt->fetchAll());
    }
    public static function generateForClass(int $classId, int $roomId, array $periods): array {
        // periods: list of [day_of_week, start_time, end_time]
        // assigns faculty based on primary assignment if available.

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Pick primary faculty for the class
            $stmt = $pdo->prepare(
                "SELECT faculty_id
                 FROM faculty_assignments
                 WHERE class_id=?
                 ORDER BY (role='Primary') DESC, assignment_id ASC
                 LIMIT 1"
            );
            $stmt->execute([$classId]);
            $facultyId = (int)($stmt->fetch()['faculty_id'] ?? 0);
            if (!$facultyId) {
                throw new RuntimeException('Assign a faculty to this class before generating.');
            }

            // Determine required weekly hours from course
            $stmt = $pdo->prepare(
                "SELECT c.weekly_hours
                 FROM class_offerings co
                 JOIN courses c ON c.course_id=co.course_id
                 WHERE co.class_id=?"
            );
            $stmt->execute([$classId]);
            $weeklyHours = (int)($stmt->fetch()['weekly_hours'] ?? 0);
            if ($weeklyHours <= 0) {
                throw new RuntimeException('Course weekly hours not found.');
            }

            // Clear existing slots for this class (regenerate)
            $stmt = $pdo->prepare('DELETE FROM timetable_slots WHERE class_id=?');
            $stmt->execute([$classId]);

            $created = 0;
            $errors = [];

            // Greedy fill earliest periods
            foreach ($periods as $p) {
                if ($created >= $weeklyHours) break;
                [$day, $start, $end] = $p;

                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO timetable_slots(class_id,faculty_id,room_id,day_of_week,start_time,end_time) VALUES (?,?,?,?,?,?)'
                    );
                    $stmt->execute([$classId, $facultyId, $roomId, (int)$day, $start, $end]);
                    $created++;
                } catch (Throwable $e) {
                    // conflict triggers may throw; just skip this period
                    $errors[] = $e->getMessage();
                    continue;
                }
            }

            if ($created < $weeklyHours) {
                throw new RuntimeException('Could not allocate all required periods. Try a different room or free slots.');
            }

            $pdo->commit();
            return ['ok' => true, 'created' => $created, 'errors' => $errors];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function defaultPeriods(): array {
        // Teaching periods used by the Cohort Timetable UI (Mon–Fri).
        // Keep this list in sync with public/cohort_timetable.php ($periodHeaders).
        $times = [
            ['08:45:00','09:45:00'],
            ['09:45:00','10:45:00'],
            ['11:05:00','12:05:00'],
            ['12:05:00','13:05:00'],
            ['13:55:00','14:45:00'],
            ['15:00:00','15:50:00'],
            ['15:50:00','16:40:00'],
        ];

        $periods = [];
        for ($day = 1; $day <= 5; $day++) {
            foreach ($times as [$start, $end]) {
                $periods[] = [$day, $start, $end];
            }
        }
        return $periods;
    }

    public static function generateForCohort(int $cohortId, int $roomId, array $periods, string $mode = self::MODE_REQUIRED_ONLY): array {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            // Load cohort
            $stmt = $pdo->prepare('SELECT * FROM cohorts WHERE cohort_id=?');
            $stmt->execute([$cohortId]);
            $cohort = $stmt->fetch();
            if (!$cohort) {
                throw new RuntimeException('Invalid cohort.');
            }

            // Selected subjects for cohort
            $stmt = $pdo->prepare(
                "SELECT c.course_id, c.course_code, c.course_title, c.weekly_hours
                 FROM cohort_courses cc
                 JOIN courses c ON c.course_id=cc.course_id
                 WHERE cc.cohort_id=?
                 ORDER BY c.course_code"
            );
            $stmt->execute([$cohortId]);
            $subjects = $stmt->fetchAll();
            if (!$subjects) {
                throw new RuntimeException('No subjects selected for this cohort.');
            }

            // Faculty mapping must exist for each selected subject
            $stmt = $pdo->prepare('SELECT course_id, faculty_id FROM cohort_faculty WHERE cohort_id=?');
            $stmt->execute([$cohortId]);
            $facultyMap = [];
            foreach ($stmt->fetchAll() as $r) {
                $facultyMap[(int)$r['course_id']] = (int)$r['faculty_id'];
            }
            // Auto-assign missing faculty to keep generation "just work".
            // Preference: faculty in same department as the course; fallback: any faculty.
            $missingFacultyCourseIds = [];
            foreach ($subjects as $s) {
                $cid = (int)$s['course_id'];
                if (empty($facultyMap[$cid])) {
                    $missingFacultyCourseIds[] = $cid;
                }
            }

            if ($missingFacultyCourseIds) {
                $stmtCourseDept = $pdo->prepare('SELECT department FROM courses WHERE course_id=?');
                $stmtDeptFaculty = $pdo->prepare('SELECT faculty_id FROM faculty WHERE department=? ORDER BY faculty_id LIMIT 1');
                $stmtAnyFaculty = $pdo->prepare('SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1');
                $stmtUpsert = $pdo->prepare(
                    'INSERT INTO cohort_faculty(cohort_id,course_id,faculty_id) VALUES (?,?,?) '
                    . 'ON DUPLICATE KEY UPDATE faculty_id=VALUES(faculty_id)'
                );

                foreach ($missingFacultyCourseIds as $cid) {
                    $stmtCourseDept->execute([$cid]);
                    $dept = (string)($stmtCourseDept->fetch()['department'] ?? '');

                    $facultyId = 0;
                    if ($dept !== '') {
                        $stmtDeptFaculty->execute([$dept]);
                        $facultyId = (int)($stmtDeptFaculty->fetch()['faculty_id'] ?? 0);
                    }
                    if (!$facultyId) {
                        $stmtAnyFaculty->execute();
                        $facultyId = (int)($stmtAnyFaculty->fetch()['faculty_id'] ?? 0);
                    }
                    if (!$facultyId) {
                        throw new RuntimeException('No faculty records found. Add faculty before generating.');
                    }

                    $facultyMap[(int)$cid] = $facultyId;
                    $stmtUpsert->execute([$cohortId, (int)$cid, $facultyId]);
                }
            }

            // For each subject, ensure there is a class_offering tied to this cohort
            $classIdByCourse = [];
            $stmtFind = $pdo->prepare('SELECT class_id FROM class_offerings WHERE cohort_id=? AND course_id=? LIMIT 1');
            $stmtCreate = $pdo->prepare(
                'INSERT INTO class_offerings(course_id,cohort_id,section,term,batch_year,expected_strength) VALUES (?,?,?,?,?,?)'
            );
            foreach ($subjects as $s) {
                $cid = (int)$s['course_id'];
                $stmtFind->execute([$cohortId, $cid]);
                $existing = $stmtFind->fetch();
                if ($existing) {
                    $classIdByCourse[$cid] = (int)$existing['class_id'];
                    continue;
                }
                $stmtCreate->execute([
                    $cid,
                    $cohortId,
                    (string)$cohort['section'],
                    (string)$cohort['term'],
                    (int)$cohort['batch_year'],
                    (int)$cohort['expected_strength'],
                ]);
                $classIdByCourse[$cid] = (int)$pdo->lastInsertId();
            }

            // Clear existing slots for the cohort (all its class_offerings)
            $stmt = $pdo->prepare(
                'DELETE t FROM timetable_slots t JOIN class_offerings co ON co.class_id=t.class_id WHERE co.cohort_id=?'
            );
            $stmt->execute([$cohortId]);

            // Build a list of session requirements: one entry per required hour
            $requirements = [];
            $labCourseMap = [];
            foreach ($subjects as $s) {
                $cid = (int)$s['course_id'];
                $hours = (int)$s['weekly_hours'];
                $labCourseMap[$cid] = self::isLabCourse((string)($s['course_code'] ?? ''), (string)($s['course_title'] ?? ''));
                for ($i=0; $i<$hours; $i++) {
                    $requirements[] = $cid;
                }
            }

            $requiredTotal = count($requirements);

            // Spread subjects by cycling to avoid bunching
            // Example: if requirements is [A,A,A,B,B,C], reorder to [A,B,C,A,B,A]
            $counts = [];
            foreach ($requirements as $cid) $counts[$cid] = ($counts[$cid] ?? 0) + 1;
            $queue = array_keys($counts);
            sort($queue);
            $ordered = [];
            while (count($ordered) < count($requirements)) {
                foreach ($queue as $cid) {
                    if (($counts[$cid] ?? 0) > 0) {
                        $ordered[] = $cid;
                        $counts[$cid]--;
                    }
                }
            }

            // Remaining requirements to schedule. This is consumed as we successfully place sessions.
            $remaining = $ordered;

            $stmtInsert = $pdo->prepare(
                'INSERT INTO timetable_slots(class_id,faculty_id,room_id,day_of_week,start_time,end_time) VALUES (?,?,?,?,?,?)'
            );

            $stmtRooms = $pdo->prepare("SELECT room_id, room_type FROM rooms WHERE active=1 ORDER BY room_code");
            $stmtRooms->execute();
            $allRoomIds = [];
            $allLabRoomIds = [];
            $isLabByRoomId = [];
            foreach ($stmtRooms->fetchAll() as $r) {
                $rid = (int)$r['room_id'];
                $isLab = ((string)$r['room_type']) === 'Lab';
                $allRoomIds[] = $rid;
                $isLabByRoomId[$rid] = $isLab;
                if ($isLab) {
                    $allLabRoomIds[] = $rid;
                }
            }

            if (in_array(true, $labCourseMap, true) && !$allLabRoomIds) {
                throw new RuntimeException('No active lab rooms found. Add at least one room with type Lab.');
            }

            // Preload faculty lists per department (used for fallback when a faculty conflicts)
            $facultyIdsByDept = [];
            $stmt = $pdo->query('SELECT faculty_id, department FROM faculty ORDER BY faculty_id');
            foreach ($stmt->fetchAll() as $r) {
                $dept = (string)$r['department'];
                $facultyIdsByDept[$dept][] = (int)$r['faculty_id'];
            }
            $allFacultyIds = [];
            foreach ($facultyIdsByDept as $list) {
                foreach ($list as $fid) {
                    $allFacultyIds[] = $fid;
                }
            }

            // Preload course departments to avoid querying inside tight loops
            $courseDept = [];
            $stmt = $pdo->query('SELECT course_id, department FROM courses');
            foreach ($stmt->fetchAll() as $r) {
                $courseDept[(int)$r['course_id']] = (string)$r['department'];
            }

            $created = 0;
            $errors = [];
            $reqIdx = 0;

            // Rest rule: avoid assigning any faculty to consecutive periods (no immediate adjacency).
            // We do a strict pass first; if some periods remain unfilled, a relaxed backfill pass may fill them.
            $strictRest = true;

            // Try to fill periods.
            // - required_only: consume the ordered requirement list.
            // - fill_all: for each period, try multiple subjects until one fits.
            // Rotate starting point to avoid always leaving early Monday empty.
            $startIdx = 0;
            if ($mode === self::MODE_FILL_ALL && count($periods) > 0) {
                $startIdx = (int)($cohortId % count($periods));
            }
            $periodsOrdered = $startIdx > 0
                ? array_merge(array_slice($periods, $startIdx), array_slice($periods, 0, $startIdx))
                : $periods;

            foreach ($periodsOrdered as $p) {
                [$day, $start, $end] = $p;

                if (!$remaining) {
                    break;
                }

                $attempts = 0;
                $maxAttempts = max(1, count($remaining));
                $placed = false;

                while ($attempts < $maxAttempts) {
                    if (!$remaining) {
                        break;
                    }

                    if ($reqIdx >= count($remaining)) {
                        $reqIdx = 0;
                    }

                    $courseId = (int)$remaining[$reqIdx];
                    $classId = (int)$classIdByCourse[$courseId];
                    $facultyId = (int)$facultyMap[$courseId];

                    // Hard rule: a subject is taught by its mapped faculty only.
                    // This prevents one subject being split across multiple staff due to fallback.
                    if ($facultyId <= 0) {
                        $errors[] = 'No faculty mapped for course_id=' . $courseId;
                        $reqIdx++;
                        $attempts++;
                        continue;
                    }

                    if ($strictRest && self::facultyHasAdjacentSlot($pdo, $facultyId, (int)$day, $start, $end)) {
                        $errors[] = 'Faculty rest rule: avoid consecutive periods.';
                        $reqIdx++;
                        $attempts++;
                        continue;
                    }

                    // Room selection
                    $requiresLab = !empty($labCourseMap[$courseId]);
                    $roomCandidates = self::selectRoomCandidates(
                        $roomId,
                        $requiresLab,
                        $allRoomIds,
                        $allLabRoomIds,
                        $isLabByRoomId
                    );
                    if (!$roomCandidates) {
                        if ($requiresLab) {
                            throw new RuntimeException('No active lab rooms found for lab subjects.');
                        }
                        throw new RuntimeException('No active rooms found. Add rooms before generating.');
                    }

                    // Filter to rooms that are actually free in this period to avoid hammering conflict triggers.
                    $roomCandidates = self::availableRoomIds($pdo, $roomCandidates, (int)$day, $start, $end);
                    if (!$roomCandidates) {
                        $errors[] = 'No available room for this period.';
                        $reqIdx++;
                        $attempts++;
                        continue;
                    }

                    try {
                        $inserted = false;
                        foreach ($roomCandidates as $rid) {
                            try {
                                $stmtInsert->execute([$classId, $facultyId, (int)$rid, (int)$day, $start, $end]);
                                $inserted = true;
                                break;
                            } catch (Throwable $e2) {
                                $errors[] = $e2->getMessage();
                                continue;
                            }
                        }

                        if (!$inserted) {
                            throw new RuntimeException('No available room for this period.');
                        }
                        $created++;
                        $placed = true;
                        // Consume this requirement so we don't schedule extra periods beyond weekly_hours.
                        array_splice($remaining, $reqIdx, 1);
                        break;
                    } catch (Throwable $e) {
                        $errors[] = $e->getMessage();
                        $reqIdx++;
                        $attempts++;
                        continue;
                    }
                }

                // If we couldn't place anything for this period, leave it empty.
                // This means all subjects conflicted for this (room/day/time) slot.
                if (!$placed) {
                    continue;
                }
            }

            // Backfill pass: attempt to fill any remaining empty periods (fill_all only).
            // This helps when the first pass order leaves some early slots empty.
            if ($mode === self::MODE_FILL_ALL) {
                $stmtExists = $pdo->prepare(
                    'SELECT 1 FROM timetable_slots t '
                    . 'JOIN class_offerings co ON co.class_id=t.class_id '
                    . 'WHERE co.cohort_id=? AND t.day_of_week=? AND t.start_time=? AND t.end_time=? LIMIT 1'
                );

                foreach ($periods as $p) {
                    [$day, $start, $end] = $p;
                    $stmtExists->execute([$cohortId, (int)$day, $start, $end]);
                    if ($stmtExists->fetch()) {
                        continue;
                    }

                    if (!$remaining) {
                        break;
                    }

                    $attempts = 0;
                    $maxAttempts = max(1, count($remaining));
                    while ($attempts < $maxAttempts && $remaining) {
                        if ($reqIdx >= count($remaining)) {
                            $reqIdx = 0;
                        }

                        $courseId = (int)$remaining[$reqIdx];
                        $classId = (int)$classIdByCourse[$courseId];
                        $facultyId = (int)$facultyMap[$courseId];

                        if ($facultyId <= 0) {
                            continue;
                        }

                        // Strict rest: skip consecutive periods.
                        if (self::facultyHasAdjacentSlot($pdo, $facultyId, (int)$day, $start, $end)) {
                            continue;
                        }

                        $requiresLab = !empty($labCourseMap[$courseId]);
                        $roomCandidates = self::selectRoomCandidates(
                            $roomId,
                            $requiresLab,
                            $allRoomIds,
                            $allLabRoomIds,
                            $isLabByRoomId
                        );
                        $roomCandidates = self::availableRoomIds($pdo, $roomCandidates, (int)$day, $start, $end);
                        $inserted = false;
                        foreach ($roomCandidates as $rid) {
                            try {
                                $stmtInsert->execute([$classId, $facultyId, (int)$rid, (int)$day, $start, $end]);
                                $created++;
                                $inserted = true;
                                break;
                            } catch (Throwable $e3) {
                                $errors[] = $e3->getMessage();
                                continue;
                            }
                        }

                        if ($inserted) {
                            array_splice($remaining, $reqIdx, 1);
                            continue 2;
                        }

                        $reqIdx++;
                        $attempts++;
                    }
                }

                // Relaxed rest backfill: if any periods are still empty, fill them even if it causes consecutive periods.
                // This keeps the timetable complete while still preferring rest when possible.
                $stmtExists = $pdo->prepare(
                    'SELECT 1 FROM timetable_slots t '
                    . 'JOIN class_offerings co ON co.class_id=t.class_id '
                    . 'WHERE co.cohort_id=? AND t.day_of_week=? AND t.start_time=? AND t.end_time=? LIMIT 1'
                );

                foreach ($periods as $p) {
                    [$day, $start, $end] = $p;
                    $stmtExists->execute([$cohortId, (int)$day, $start, $end]);
                    if ($stmtExists->fetch()) {
                        continue;
                    }

                    if (!$remaining) {
                        break;
                    }

                    $attempts = 0;
                    $maxAttempts = max(1, count($remaining));
                    while ($attempts < $maxAttempts && $remaining) {
                        if ($reqIdx >= count($remaining)) {
                            $reqIdx = 0;
                        }

                        $courseId = (int)$remaining[$reqIdx];
                        $classId = (int)$classIdByCourse[$courseId];
                        $facultyId = (int)$facultyMap[$courseId];
                        if ($facultyId <= 0) continue;

                        $requiresLab = !empty($labCourseMap[$courseId]);
                        $roomCandidates = self::selectRoomCandidates(
                            $roomId,
                            $requiresLab,
                            $allRoomIds,
                            $allLabRoomIds,
                            $isLabByRoomId
                        );
                        $roomCandidates = self::availableRoomIds($pdo, $roomCandidates, (int)$day, $start, $end);
                        $inserted = false;
                        foreach ($roomCandidates as $rid) {
                            try {
                                $stmtInsert->execute([$classId, $facultyId, (int)$rid, (int)$day, $start, $end]);
                                $created++;
                                $inserted = true;
                                break;
                            } catch (Throwable $e4) {
                                $errors[] = $e4->getMessage();
                                continue;
                            }
                        }

                        if ($inserted) {
                            array_splice($remaining, $reqIdx, 1);
                            continue 2;
                        }

                        $reqIdx++;
                        $attempts++;
                    }
                }
            }

            if (!$remaining && $mode !== self::MODE_FILL_ALL) {
                // required_only: fully scheduled
            } elseif ($mode !== self::MODE_FILL_ALL && $remaining) {
                throw new RuntimeException('Could not allocate all subject periods. Try different room or reduce conflicts.');
            }

            if ($mode === self::MODE_FILL_ALL) {
                // Report how full the grid is for better UX
                $stmtCount = $pdo->prepare(
                    'SELECT COUNT(*) AS c '
                    . 'FROM timetable_slots t '
                    . 'JOIN class_offerings co ON co.class_id=t.class_id '
                    . 'WHERE co.cohort_id=?'
                );
                $stmtCount->execute([$cohortId]);
                $gridFilled = (int)($stmtCount->fetch()['c'] ?? 0);
                $gridTotal = count($periods);
                $scheduledRequired = max(0, $requiredTotal - count($remaining));
                $unallocatedRequired = max(0, $requiredTotal - $scheduledRequired);
                $pdo->commit();
                return [
                    'ok' => true,
                    'created' => $created,
                    // Required sessions (based on weekly_hours)
                    'filled' => $scheduledRequired,
                    'total' => $requiredTotal,
                    'unfilled' => $unallocatedRequired,
                    // Grid stats (based on available periods)
                    'grid_filled' => $gridFilled,
                    'grid_total' => $gridTotal,
                    'grid_unfilled' => max(0, $gridTotal - $gridFilled),
                    'errors' => $errors,
                ];
            }

            $pdo->commit();
            return ['ok' => true, 'created' => $created, 'errors' => $errors];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function generateForCohorts(array $cohortIds, int $roomId, array $periods, string $mode = self::MODE_REQUIRED_ONLY): array {
        $cohortIds = array_values(array_unique(array_map('intval', $cohortIds)));
        $cohortIds = array_values(array_filter($cohortIds, fn($id) => $id > 0));
        if (!$cohortIds) {
            return ['ok' => false, 'error' => 'No classes selected.'];
        }

        $results = [];
        $okCount = 0;
        foreach ($cohortIds as $cid) {
            $res = self::generateForCohort((int)$cid, $roomId, $periods, $mode);
            $summary = null;
            if (!empty($res['ok']) && isset($res['filled'], $res['total'], $res['unfilled'])) {
                $top = [];
                foreach (($res['errors'] ?? []) as $msg) {
                    $top[$msg] = ($top[$msg] ?? 0) + 1;
                }
                arsort($top);
                $top = array_slice($top, 0, 3, true);

                $summary = [
                    'filled' => (int)$res['filled'],
                    'total' => (int)$res['total'],
                    'unfilled' => (int)$res['unfilled'],
                    'top_conflicts' => $top,
                ];
            }

            $results[] = ['cohort_id' => (int)$cid, 'result' => $res, 'summary' => $summary];
            if (!empty($res['ok'])) {
                $okCount++;
            }
        }

        return [
            'ok' => true,
            'count' => count($cohortIds),
            'ok_count' => $okCount,
            'results' => $results,
        ];
    }
}
