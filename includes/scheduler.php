<?php
// ------------------------------------------------------------
// Random schedule generator
// ------------------------------------------------------------
// Builds a full timetable for a block automatically:
//   * 4 random class days out of Mon-Fri (different mix per block)
//   * random subjects, times, durations and rooms
//   * random teacher + room per subject — never double-books either
//     (checks day + time overlap against every existing schedule)
// ------------------------------------------------------------

function generate_block_schedule($block_id) {
    $conn     = db();
    $block_id = (int)$block_id;

    // ---- subject pool: reuse subjects already used in the system ----
    $pool = array_column(
        $conn->query("SELECT DISTINCT subject FROM schedules")->fetch_all(MYSQLI_ASSOC),
        'subject'
    );
    if (!$pool) {
        $pool = ['IT 101', 'IT 102', 'GEC 101', 'GEC 102', 'PE 101', 'NSTP 101', 'MST 410', 'RIZAL'];
    }
    shuffle($pool);
    $subjects = array_slice($pool, 0, min(6, count($pool)));

    // ---- 4 random class days (Mon-Fri) ----
    $days = ['M', 'T', 'W', 'TH', 'F'];
    shuffle($days);
    $days = array_slice($days, 0, 4);

    // ---- each subject meets twice; balance ~3 sessions per day ----
    $dayLoad  = array_fill_keys($days, 0);
    $meetings = []; // subject => [day, day]
    foreach ($subjects as $subj) {
        $cand = $days;
        shuffle($cand);
        usort($cand, function ($a, $b) use ($dayLoad) {
            return $dayLoad[$a] <=> $dayLoad[$b];
        });
        $meetings[$subj] = [$cand[0], $cand[1]];
        $dayLoad[$cand[0]]++;
        $dayLoad[$cand[1]]++;
    }

    // ---- lay out each day: random start, durations and breaks ----
    $entries = []; // [day, subject, start_min, end_min]
    foreach ($days as $d) {
        $todays = [];
        foreach ($meetings as $subj => $ds) {
            if (in_array($d, $ds)) $todays[] = $subj;
        }
        if (!$todays) continue;
        shuffle($todays);

        $dur = array_map(function () {
            return [60, 90, 90, 120][random_int(0, 3)];
        }, $todays);
        $brk = [];
        for ($i = 0; $i < count($todays) - 1; $i++) {
            $brk[] = [0, 15, 30][random_int(0, 2)];
        }

        // if the day would run past 20:00, tighten it up
        $window = 20 * 60 - 7 * 60;
        if (array_sum($dur) + array_sum($brk) > $window) {
            $dur = array_fill(0, count($dur), 60);
            $brk = array_fill(0, count($brk), 0);
        }
        $total  = array_sum($dur) + array_sum($brk);
        $latest = 20 * 60 - $total;

        $t = 7 * 60 + 15 * random_int(0, max(0, (int)floor(($latest - 7 * 60) / 15)));
        foreach ($todays as $i => $subj) {
            $entries[] = [$d, $subj, $t, $t + $dur[$i]];
            $t += $dur[$i] + ($brk[$i] ?? 0);
        }
    }

    // ---- teacher assignment (conflict-free across ALL blocks) ----
    $teachers = array_column(
        $conn->query("SELECT name FROM teachers ORDER BY name")->fetch_all(MYSQLI_ASSOC),
        'name'
    );

    // max scheduled load per teacher (keeps workloads balanced)
    $MAX_LOAD = 18 * 60; // minutes per week
    $load = [];
    foreach ($conn->query(
        "SELECT instructor, SUM(TIME_TO_SEC(TIMEDIFF(time_end,time_start)))/60 AS m
         FROM schedules GROUP BY instructor"
    )->fetch_all(MYSQLI_ASSOC) as $r) {
        $load[$r['instructor']] = (int)$r['m'];
    }
    // pick a random free teacher that stays under the cap once $add minutes
    // are included; if every free teacher would exceed, take the least-loaded
    $pick_teacher = function (array $free, int $add = 0) use ($load, $MAX_LOAD) {
        if (!$free) return null;
        shuffle($free);
        usort($free, function ($a, $b) use ($load) {
            return ($load[$a] ?? 0) <=> ($load[$b] ?? 0);
        });
        foreach ($free as $t) {
            if (($load[$t] ?? 0) + $add <= $MAX_LOAD) return $t;
        }
        return $free[0];
    };
    $busyStmt = $conn->prepare(
        "SELECT DISTINCT instructor FROM schedules
         WHERE day_of_week=? AND time_start < ? AND time_end > ?"
    );
    $free_for = function (array $slots) use ($teachers, $busyStmt) {
        $busy = [];
        foreach ($slots as $sl) {
            $end   = sprintf('%02d:%02d', intdiv($sl[3], 60), $sl[3] % 60);
            $start = sprintf('%02d:%02d', intdiv($sl[2], 60), $sl[2] % 60);
            $busyStmt->bind_param('sss', $sl[0], $end, $start);
            $busyStmt->execute();
            foreach (array_column($busyStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'instructor') as $t) {
                $busy[$t] = true;
            }
        }
        return array_values(array_diff($teachers, array_keys($busy)));
    };

    // ---- rooms: existing rooms + defaults, conflict-checked like teachers ----
    $roomPool = array_column(
        $conn->query("SELECT DISTINCT room FROM schedules WHERE room IS NOT NULL AND room NOT IN ('','TBA')")->fetch_all(MYSQLI_ASSOC),
        'room'
    );
    $roomPool = array_values(array_unique(array_merge(
        $roomPool, ['3064 LEC', '3064 CLS', '3021 LEC', '3021 CLS', '3022 LEC', '410', 'AVR', 'GYM']
    )));
    $roomStmt = $conn->prepare(
        "SELECT DISTINCT room FROM schedules
         WHERE day_of_week=? AND time_start < ? AND time_end > ? AND room <> ''"
    );
    $free_rooms = function (array $slots) use ($roomPool, $roomStmt) {
        $busy = [];
        foreach ($slots as $sl) {
            $end   = sprintf('%02d:%02d', intdiv($sl[3], 60), $sl[3] % 60);
            $start = sprintf('%02d:%02d', intdiv($sl[2], 60), $sl[2] % 60);
            $roomStmt->bind_param('sss', $sl[0], $end, $start);
            $roomStmt->execute();
            foreach (array_column($roomStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'room') as $r) {
                $busy[$r] = true;
            }
        }
        return array_values(array_diff($roomPool, array_keys($busy)));
    };

    $ins = $conn->prepare(
        "INSERT INTO schedules (block_id, day_of_week, time_start, time_end, subject, instructor, room)
         VALUES (?,?,?,?,?,?,?)"
    );

    $bySubj = [];
    foreach ($entries as $e) $bySubj[$e[1]][] = $e;

    foreach ($bySubj as $subj => $slots) {
        // prefer ONE teacher and ONE room for all of the subject's sessions
        $subjMin = array_sum(array_map(function ($sl) { return $sl[3] - $sl[2]; }, $slots));
        $teacher = $pick_teacher($free_for($slots), $subjMin);
        $freeR   = $free_rooms($slots);
        $room    = $freeR ? $freeR[random_int(0, count($freeR) - 1)] : null;

        foreach ($slots as $sl) {
            $t = $teacher;
            if (!$t) { // fallback: any teacher free for this slot alone
                $t = $pick_teacher($free_for([$sl]), $sl[3] - $sl[2]) ?? 'TBA';
            }
            if ($t !== 'TBA') $load[$t] = ($load[$t] ?? 0) + ($sl[3] - $sl[2]);
            $r = $room;
            if (!$r) { // fallback: any room free for this slot alone
                $f = $free_rooms([$sl]);
                $r = $f ? $f[random_int(0, count($f) - 1)] : 'TBA';
            }
            $start = sprintf('%02d:%02d', intdiv($sl[2], 60), $sl[2] % 60);
            $end   = sprintf('%02d:%02d', intdiv($sl[3], 60), $sl[3] % 60);
            $ins->bind_param('issssss', $block_id, $sl[0], $start, $end, $subj, $t, $r);
            $ins->execute();
        }
    }
}
