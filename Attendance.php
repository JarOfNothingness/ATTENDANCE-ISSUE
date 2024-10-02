<?php
session_start(); // Start the session at the very top

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include("../LoginRegisterAuthentication/connection.php");
include("../crud/headerforattendance.php");

// Fetch distinct values for filters
$grade_levels_query = "SELECT DISTINCT gradeLevel FROM sf2_attendance_report ORDER BY gradeLevel";
$grade_levels_result = mysqli_query($connection, $grade_levels_query);

$sections_query = "SELECT DISTINCT section FROM sf2_attendance_report ORDER BY section";
$sections_result = mysqli_query($connection, $sections_query);

$learners_query = "SELECT id, learners_name, school_id, grade, section FROM students ORDER BY learners_name";
$learners_result = mysqli_query($connection, $learners_query);

// Fetch distinct school years for the dropdown
$school_years_query = "SELECT DISTINCT school_year FROM students ORDER BY school_year";
$school_years_result = mysqli_query($connection, $school_years_query);

// Fetch distinct subjects and quarters for the filters
$subjects_query = "SELECT DISTINCT subject FROM enrollments ORDER BY subject";
$subjects_result = mysqli_query($connection, $subjects_query);

$quarters_query = "SELECT DISTINCT quarter FROM enrollments ORDER BY quarter";
$quarters_result = mysqli_query($connection, $quarters_query);

// Handle filtering of records
$filters = [];
$filter_sql = '';

if (isset($_POST['filter'])) {
    if (!empty($_POST['grade_level'])) {
        $filters[] = "ar.gradeLevel = '" . mysqli_real_escape_string($connection, $_POST['grade_level']) . "'";
    }
    if (!empty($_POST['section'])) {
        $filters[] = "ar.section = '" . mysqli_real_escape_string($connection, $_POST['section']) . "'";
    }
    if (!empty($_POST['learner_name'])) {
        $filters[] = "ar.learnerName = '" . mysqli_real_escape_string($connection, $_POST['learner_name']) . "'";
    }
    if (!empty($_POST['school_year'])) {
        $filters[] = "ar.schoolYear = '" . mysqli_real_escape_string($connection, $_POST['school_year']) . "'";
    }
    if (!empty($_POST['month'])) {
        $filters[] = "ar.month = '" . mysqli_real_escape_string($connection, $_POST['month']) . "'";
    }
    if (!empty($_POST['subject'])) {
        $filters[] = "e.subject = '" . mysqli_real_escape_string($connection, $_POST['subject']) . "'";
    }
    if (!empty($_POST['quarter'])) {
        $filters[] = "e.quarter = '" . mysqli_real_escape_string($connection, $_POST['quarter']) . "'";
    }

    if (count($filters) > 0) {
        $filter_sql = 'WHERE ' . implode(' AND ', $filters);
    }
}

// Modify query to include subject and performance task data
$query = "
    SELECT ar.*, sg.performance_task, e.subject, e.quarter
    FROM sf2_attendance_report ar
    LEFT JOIN student_grades sg ON ar.schoolId = sg.student_id
    LEFT JOIN enrollments e ON ar.learnerName = e.learners_name
    $filter_sql
";

$result = mysqli_query($connection, $query);

if (!$result) {
    die('Query failed: ' . mysqli_error($connection)); // Debugging: Check if the query fails
}

// Function to calculate performance task score (existing function)
function calculatePerformanceTask($row, $subject_id) {
    global $connection; // Include the database connection in the function

    // Performance task calculation logic (no changes needed)
    $total_days = 31;
    $present_days = 0;
    $absent_days = 0;
    $late_days = 0;
    $excused_days = 0;

    for ($i = 1; $i <= $total_days; $i++) {
        $day_status = $row['day_' . str_pad($i, 2, '0', STR_PAD_LEFT)];
        if ($day_status == 'P') $present_days++;
        if ($day_status == 'A') $absent_days++;
        if ($day_status == 'L') $late_days++;
        if ($day_status == 'E') $excused_days++;
    }

    $total_possible_days = 31;
    $max_score = 100;
    
    $present_score = ($present_days / $total_possible_days) * $max_score;
    $absent_penalty = ($absent_days * 1.5);
    $late_penalty = ($late_days * 0.5);
    $excused_penalty = ($excused_days * 0.2);

    $performance_score = $present_score - $absent_penalty - $late_penalty - $excused_penalty;
    $performance_score = max($performance_score, 0);

    $weights = [
        'ENGLISH' => 0.50,
        'MATH' => 0.40,
        'SCIENCE' => 0.40,
        'FILIPINO' => 0.50,
        'TLE' => 0.60,
        'MAPEH' => 0.60,
        'ARALING PANLIPUNAN' => 0.50,
        'ESP' => 0.50,
        'VALUES' => 0.50,
    ];

    $performance_weight = isset($weights[$subject_id]) ? $weights[$subject_id] : 0.50;
    $performance_task = $performance_score * $performance_weight;

    $performance_task = round($performance_task, 2);

    $student_id = $row['schoolId'];
    $update_query = "UPDATE student_grades SET performance_task = ? WHERE student_id = ? AND subject_id = ?";
    $stmt = mysqli_prepare($connection, $update_query);
    mysqli_stmt_bind_param($stmt, "dii", $performance_task, $student_id, $subject_id);
    mysqli_stmt_execute($stmt);
    
    return $performance_task;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">
    <style>
        /* Custom styles for table */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            table-layout: fixed;
        }

        th, td {
            text-align: center;
            white-space: nowrap; /* Prevent text from wrapping */
        }

        th {
            background-color: #343a40;
            color: white;
        }

        td {
            border: 1px solid #dee2e6;
        }

        thead th {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        /* Ensure that table columns are evenly distributed */
table {
    table-layout: auto;
}

/* Set a minimum width for table cells */
td, th {
    min-width: 60px;
}

/* Style for the header */
thead th {
    background-color: #343a40;
    color: #fff;
    text-align: center;
}
/* Set a fixed width for each day column if necessary */
table td:nth-child(n+4):nth-child(-n+34) {
    width: 40px; /* Adjust as needed */
}

    </style>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#attendanceTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "scrollX": true // Enable horizontal scrolling
            });
        });
    </script>
</head>
<body>
<div class="container mt-5">
    <h2>Attendance Records</h2>
    
    <!-- Filter Form -->
    <div class="mb-4">
        <h3>Filter Attendance Records</h3>
        <form method="POST" action="" class="row g-3">
            <div class="col-md-3">
                <label for="grade_level" class="form-label">Grade Level:</label>
                <select name="grade_level" id="grade_level" class="form-control">
                    <option value="">Select Grade Level</option>
                    <?php while ($row = mysqli_fetch_assoc($grade_levels_result)) { ?>
                        <option value="<?php echo htmlspecialchars($row['gradeLevel']); ?>">
                            <?php echo htmlspecialchars($row['gradeLevel']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
    <label for="subject" class="form-label">Subject:</label>
    <select name="subject" id="subject" class="form-control">
        <option value="">Select Subject</option>
        <?php
        $subjects_query = "SELECT DISTINCT subject FROM enrollments ORDER BY subject";
        $subjects_result = mysqli_query($connection, $subjects_query);
        while ($row = mysqli_fetch_assoc($subjects_result)) { ?>
            <option value="<?php echo htmlspecialchars($row['subject']); ?>">
                <?php echo htmlspecialchars($row['subject']); ?>
            </option>
        <?php } ?>
    </select>
</div>

<div class="col-md-3">
    <label for="quarter" class="form-label">Quarter:</label>
    <select name="quarter" id="quarter" class="form-control">
        <option value="">Select Quarter</option>
        <option value="1">First Quarter</option>
        <option value="2">Second Quarter</option>
        <option value="3">Third Quarter</option>
        <option value="4">Fourth Quarter</option>
    </select>
</div>
            <div class="col-md-3">
                <label for="section" class="form-label">Section:</label>
                <select name="section" id="section" class="form-control">
                    <option value="">Select Section</option>
                    <?php while ($row = mysqli_fetch_assoc($sections_result)) { ?>
                        <option value="<?php echo htmlspecialchars($row['section']); ?>">
                            <?php echo htmlspecialchars($row['section']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="learner_name" class="form-label">Learner Name:</label>
                <select name="learner_name" id="learner_name" class="form-control">
                    <option value="">Select Learner</option>
                    <?php while ($row = mysqli_fetch_assoc($learners_result)) { ?>
                        <option value="<?php echo htmlspecialchars($row['learners_name']); ?>">
                            <?php echo htmlspecialchars($row['learners_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="school_year" class="form-label">School Year:</label>
                <select name="school_year" id="school_year" class="form-control">
                    <option value="">Select School Year</option>
                    <?php while ($row = mysqli_fetch_assoc($school_years_result)) { ?>
                        <option value="<?php echo htmlspecialchars($row['school_year']); ?>">
                            <?php echo htmlspecialchars($row['school_year']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="month" class="form-label">Month:</label>
                <input type="month" name="month" id="month" class="form-control">
            </div>
            <div class="col-12">
                <button type="submit" name="filter" class="btn btn-primary mt-3">Filter Records</button>
                <a href="AddAttendance.php" class="btn btn-secondary mt-3 ml-2">Add New Record</a>
                
            </div>
        </form>
    </div>


    <h2>Attendance Records</h2>
    <!-- Data Table -->
    <div class="table-responsive">
        <table id="attendanceTable" class="table table-bordered table-striped table-sm">
            <thead class="thead-dark">
                <tr>
                    <th rowspan="2">Learner Name</th>
                    <th rowspan="2">Grade Level</th>
                    <th rowspan="2">Section</th>
                    <th rowspan="2">Subject</th>
                    <th rowspan="2">Quarter</th> <!-- New Quarter Column --> <!-- New Subject Column -->
                    <!-- Week Headers -->
                    <th colspan="7">Week 1</th>
                    <th colspan="7">Week 2</th>
                    <th colspan="7">Week 3</th>
                    <th colspan="7">Week 4</th>
                    <th colspan="3">Week 5</th>
                    <th rowspan="2">Month</th> <!-- Add the Month column -->
                    <th rowspan="2">Total Present</th>
                    <th rowspan="2">Total Absent</th>
                    <th rowspan="2">Total Late</th>
                    <th rowspan="2">Total Excused</th>
                    <th rowspan="2">Performance Task</th>
                    <th rowspan="2">Remarks</th>
                    <th rowspan="2"> Action</th>
                </tr>
                <tr>
                    <!-- Week 1 Days -->
                    <?php for ($i = 1; $i <= 7; $i++) { echo "<th>Day " . str_pad($i, 2, '0', STR_PAD_LEFT) . "</th>"; } ?>
                    <!-- Week 2 Days -->
                    <?php for ($i = 8; $i <= 14; $i++) { echo "<th>Day " . str_pad($i, 2, '0', STR_PAD_LEFT) . "</th>"; } ?>
                    <!-- Week 3 Days -->
                    <?php for ($i = 15; $i <= 21; $i++) { echo "<th>Day " . str_pad($i, 2, '0', STR_PAD_LEFT) . "</th>"; } ?>
                    <!-- Week 4 Days -->
                    <?php for ($i = 22; $i <= 28; $i++) { echo "<th>Day " . str_pad($i, 2, '0', STR_PAD_LEFT) . "</th>"; } ?>
                    <!-- Week 5 Days -->
                    <?php for ($i = 29; $i <= 31; $i++) { echo "<th>Day " . str_pad($i, 2, '0', STR_PAD_LEFT) . "</th>"; } ?>
                </tr>
            </thead>
            <tbody>
    <?php while ($row = mysqli_fetch_assoc($result)) {
        $performance_task = calculatePerformanceTask($row, $row['subject_id']);
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['learnerName']); ?></td>
        <td><?php echo htmlspecialchars($row['gradeLevel']); ?></td>
        <td><?php echo htmlspecialchars($row['section']); ?></td>
        <td><?php echo htmlspecialchars($row['subject']); ?></td>
        <td><?php echo htmlspecialchars($row['quarter']); ?></td>
        <!-- Data for Week 1 -->
        <?php for ($i = 1; $i <= 7; $i++) { echo "<td>" . htmlspecialchars($row['day_' . str_pad($i, 2, '0', STR_PAD_LEFT)]) . "</td>"; } ?>
        <!-- Data for Week 2 -->
        <?php for ($i = 8; $i <= 14; $i++) { echo "<td>" . htmlspecialchars($row['day_' . str_pad($i, 2, '0', STR_PAD_LEFT)]) . "</td>"; } ?>
        <!-- Data for Week 3 -->
        <?php for ($i = 15; $i <= 21; $i++) { echo "<td>" . htmlspecialchars($row['day_' . str_pad($i, 2, '0', STR_PAD_LEFT)]) . "</td>"; } ?>
        <!-- Data for Week 4 -->
        <?php for ($i = 22; $i <= 28; $i++) { echo "<td>" . htmlspecialchars($row['day_' . str_pad($i, 2, '0', STR_PAD_LEFT)]) . "</td>"; } ?>
        <!-- Data for Week 5 -->
        <?php for ($i = 29; $i <= 31; $i++) { echo "<td>" . htmlspecialchars($row['day_' . str_pad($i, 2, '0', STR_PAD_LEFT)]) . "</td>"; } ?>
        <td><?php echo htmlspecialchars($row['month']); ?></td> <!-- Add Month data here -->
        <td><?php echo htmlspecialchars($row['total_present']); ?></td>
        <td><?php echo htmlspecialchars($row['total_absent']); ?></td>
        <td><?php echo htmlspecialchars($row['total_late']); ?></td>
        <td><?php echo htmlspecialchars($row['total_excused']); ?></td>
        <td><?php echo number_format($performance_task, 2); ?></td>
        <td><?php echo htmlspecialchars($row['remarks']); ?></td>
        <td>
            <a href="update_attendance.php?id=<?php echo $row['form2Id']; ?>">Edit</a>
        </td>
    </tr>
    <?php } ?>
</tbody>

        </table>
    </div>
</div>  



<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
