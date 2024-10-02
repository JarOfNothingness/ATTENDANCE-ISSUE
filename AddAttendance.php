<?php
session_start(); // Start the session at the very top

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

ob_start(); // Start output buffering

include('../crud/header.php'); 
include("../LoginRegisterAuthentication/connection.php");

// Fetch distinct values for dropdowns
$grade_levels_query = "SELECT DISTINCT grade FROM students ORDER BY grade";
$grade_levels_result = mysqli_query($connection, $grade_levels_query);

$sections_query = "SELECT DISTINCT section FROM students ORDER BY section";
$sections_result = mysqli_query($connection, $sections_query);

$learners_query = "SELECT DISTINCT learners_name, school_id, grade, section, school_year FROM students ORDER BY learners_name";
$learners_result = mysqli_query($connection, $learners_query);

$subjects_query = "SELECT DISTINCT name FROM subjects ORDER BY name";
$subjects_result = mysqli_query($connection, $subjects_query);

// Fetch distinct school years for the dropdown
$school_years_query = "SELECT DISTINCT school_year FROM students ORDER BY school_year";
$school_years_result = mysqli_query($connection, $school_years_query);

// Handle form submission for adding attendance record
if (isset($_POST['add_attendance'])) {
    $learner_name = mysqli_real_escape_string($connection, $_POST['learner_name']);
    $school_id = mysqli_real_escape_string($connection, $_POST['school_id']);
    $grade_level = mysqli_real_escape_string($connection, $_POST['grade_level']);
    $section = mysqli_real_escape_string($connection, $_POST['section']);
    $subject = mysqli_real_escape_string($connection, $_POST['subject']);
    $quarter = mysqli_real_escape_string($connection, $_POST['quarter']); // New quarter field
    $school_year = mysqli_real_escape_string($connection, $_POST['school_year']);
    $month = mysqli_real_escape_string($connection, $_POST['month']);
    
    $days = [];
    for ($i = 1; $i <= 31; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        $days[$day] = mysqli_real_escape_string($connection, $_POST["day_$day"]);
    }

    $total_present = array_count_values($days)['Present'] ?? 0;
    $total_absent = array_count_values($days)['Absent'] ?? 0;
    $total_late = array_count_values($days)['Late'] ?? 0;
    $total_excused = array_count_values($days)['Excused'] ?? 0;

    // Insert only for the selected subject and quarter
    $query = "INSERT INTO sf2_attendance_report (schoolId, learnerName, gradeLevel, section, subject, quarter, schoolYear, month, total_present, total_absent, total_late, total_excused, ";
    for ($i = 1; $i <= 31; $i++) {
        $query .= "day_" . str_pad($i, 2, '0', STR_PAD_LEFT) . ", ";
    }
    $query .= "remarks) VALUES ('$school_id', '$learner_name', '$grade_level', '$section', '$subject', '$quarter', '$school_year', '$month', '$total_present', '$total_absent', '$total_late', '$total_excused', ";
    for ($i = 1; $i <= 31; $i++) {
        $query .= "'" . $days[str_pad($i, 2, '0', STR_PAD_LEFT)] . "', ";
    }
    $query .= "'')";

    // Remove trailing comma and space from the query
    $query = rtrim($query, ', ');
    
    if (mysqli_query($connection, $query)) {
        header("Location: Attendance.php"); // Redirect to Attendance.php after successful record addition
        ob_end_flush(); 
        exit();
    } else {
        echo "Error: " . mysqli_error($connection);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Attendance Record</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function populateStudentDetails() {
            const select = document.getElementById('learner_name');
            const selectedOption = select.options[select.selectedIndex];
            document.getElementById('school_id').value = selectedOption.getAttribute('data-school-id') || '';
            document.getElementById('grade_level').value = selectedOption.getAttribute('data-grade-level') || '';
            document.getElementById('section').value = selectedOption.getAttribute('data-section') || '';
            document.getElementById('school_year').value = selectedOption.getAttribute('data-school-year') || ''; // Populate school year
        }
    </script>
</head>
<body>
<div class="container mt-5">
    <h2>Add New Attendance Record</h2>

    <!-- Form to Add New Attendance Record -->
    <form method="POST" action="" class="row g-3">
        <!-- Learner Name Dropdown -->
        <div class="col-md-6">
            <label for="learner_name" class="form-label">Learner Name:</label>
            <select name="learner_name" id="learner_name" class="form-control" required onchange="populateStudentDetails()">
                <option value="">Select Learner</option>
                <?php while ($student = mysqli_fetch_assoc($learners_result)) { ?>
                    <option value="<?php echo htmlspecialchars($student['learners_name']); ?>"
                        data-school-id="<?php echo htmlspecialchars($student['school_id']); ?>"
                        data-grade-level="<?php echo htmlspecialchars($student['grade']); ?>"
                        data-section="<?php echo htmlspecialchars($student['section']); ?>"
                        data-school-year="<?php echo htmlspecialchars($student['school_year']); ?>"> <!-- Include school year -->
                        <?php echo htmlspecialchars($student['learners_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- Hidden Fields to Store Selected Student Details -->
        <div class="col-md-6 d-none">
            <input type="hidden" id="school_id" name="school_id">
            <input type="hidden" id="grade_level" name="grade_level">
        </div>

        <!-- Section and Subject Fields -->
        <div class="col-md-6">
            <label for="section" class="form-label">Section:</label>
            <input type="text" name="section" id="section" class="form-control" readonly> <!-- Change to input and readonly -->
        </div>

        <div class="col-md-6">
            <label for="subject" class="form-label">Subject:</label>
            <select name="subject" id="subject" class="form-control" required>
                <option value="">Select Subject</option>
                <?php while ($subject = mysqli_fetch_assoc($subjects_result)) { ?>
                    <option value="<?php echo htmlspecialchars($subject['name']); ?>">
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- School Year Field -->
        <div class="col-md-6">
            <label for="school_year" class="form-label">School Year:</label>
            <input type="text" name="school_year" id="school_year" class="form-control" readonly> <!-- Change to input and readonly -->
        </div>

        <!-- Quarter Field (New) -->
        <div class="col-md-6">
            <label for="quarter" class="form-label">Quarter:</label>
            <select name="quarter" id="quarter" class="form-control" required>
                <option value="1">Quarter 1</option>
                <option value="2">Quarter 2</option>
                <option value="3">Quarter 3</option>
                <option value="4">Quarter 4</option>
            </select>
        </div>

        <!-- Month Field -->
        <div class="col-md-6">
            <label for="month" class="form-label">Month:</label>
            <input type="month" name="month" id="month" class="form-control" required>
        </div>

        <!-- Group Days into Weeks (Unchanged) -->
        <div class="col-md-12">
            <div class="accordion" id="attendanceAccordion">
                <?php 
                $week_start = 1;
                for ($week = 1; $week <= 5; $week++) {
                    $week_end = min($week_start + 6, 31);
                ?>
                <div class="card">
                    <div class="card-header" id="heading<?php echo $week; ?>">
                        <h5 class="mb-0">
                            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse<?php echo $week; ?>" aria-expanded="true" aria-controls="collapse<?php echo $week; ?>">
                                Week <?php echo $week; ?> (Days <?php echo $week_start; ?> - <?php echo $week_end; ?>)
                            </button>
                        </h5>
                    </div>

                    <div id="collapse<?php echo $week; ?>" class="collapse<?php echo $week === 1 ? ' show' : ''; ?>" aria-labelledby="heading<?php echo $week; ?>" data-parent="#attendanceAccordion">
                        <div class="card-body">
                            <div class="row">
                                <?php for ($i = $week_start; $i <= $week_end; $i++): ?>
                                <div class="col-md-2">
                                    <label for="day_<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" class="form-label">Day <?php echo $i; ?>:</label>
                                    <select name="day_<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" id="day_<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" class="form-control">
                                        <option value="">Status</option>
                                        <option value="Present">Present</option>
                                        <option value="Absent">Absent</option>
                                        <option value="Late">Late</option>
                                        <option value="Excused">Excused</option>
                                    </select>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                    $week_start += 7;
                }
                ?>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" name="add_attendance" class="btn btn-primary mt-3">Add Record</button>
            <a href="Attendance.php" class="btn btn-secondary mt-3 ml-2">Back to Attendance</a>
        </div>
    </form>
</div>

<!-- Include Bootstrap JavaScript and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php include('../crud/footer.php'); ?>
