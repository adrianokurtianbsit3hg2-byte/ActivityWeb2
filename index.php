<?php
include("config.php");
include("firebaseRDB.php");
session_start();
if (!isset($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');


$db = new firebaseRDB($databaseURL);
$data = $db->retrieve("faculty");
$data = json_decode($data, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty List</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<!-- TOP HEADER -->
<div class="top-header">
    <h2>Faculty Onsite Class Monitoring System</h2>
</div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="#" class="active">Faculty List</a>
    <a href="schedule.php">Schedule</a>
    <a href="attendance.php">Attendance</a>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    <h1>Faculty List</h1>

    <!-- ADD RECORD -->
    <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
        Add New Faculty
    </button>

    <!-- TABLE -->
    <table id="facultyTable" class="table table-striped table-bordered table-hover custom-table">
        <thead class="table-light">
            <tr class="text-center">
                <th>ID No</th>
                <th>Name</th>
                <th>Department</th>
                <th>Faculty Rank</th>
                <th>Faculty Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (is_array($data)) : ?>
                <?php foreach ($data as $id => $faculty) : ?>
                    <tr>
                        <td><?= htmlspecialchars($faculty['Id_No']) ?></td>
                        <td><?= htmlspecialchars($faculty['Last_Name']) ?>, <?= htmlspecialchars($faculty['First_Name']) ?></td>
                        <td><?= htmlspecialchars($faculty['Department']) ?></td>
                        <td><?= htmlspecialchars($faculty['Faculty_Rank']) ?></td>
                        <td><?= htmlspecialchars($faculty['Faculty_Status']) ?></td>
                        <td class="text-center">
                            <button 
                                class="btn btn-sm btn-dark viewBtn"
                                data-id="<?= $id ?>"
                                data-idno="<?= htmlspecialchars($faculty['Id_No']) ?>"
                                data-last="<?= htmlspecialchars($faculty['Last_Name']) ?>"
                                data-first="<?= htmlspecialchars($faculty['First_Name']) ?>"
                                data-middle="<?= htmlspecialchars($faculty['Middle_Name']) ?>"
                                data-gender="<?= htmlspecialchars($faculty['Gender']) ?>"
                                data-rank="<?= htmlspecialchars($faculty['Faculty_Rank']) ?>"
                                data-status="<?= htmlspecialchars($faculty['Faculty_Status']) ?>"
                                data-department="<?= htmlspecialchars($faculty['Department']) ?>"
                            >View</button>
                            <button 
                                class="btn btn-sm btn-primary editBtn "
                                data-id="<?= $id ?>"
                                data-idno="<?= htmlspecialchars($faculty['Id_No']) ?>"
                                data-last="<?= htmlspecialchars($faculty['Last_Name']) ?>"
                                data-first="<?= htmlspecialchars($faculty['First_Name']) ?>"
                                data-middle="<?= htmlspecialchars($faculty['Middle_Name']) ?>"
                                data-gender="<?= htmlspecialchars($faculty['Gender']) ?>"
                                data-rank="<?= htmlspecialchars($faculty['Faculty_Rank']) ?>"
                                data-status="<?= htmlspecialchars($faculty['Faculty_Status']) ?>"
                                data-department="<?= htmlspecialchars($faculty['Department']) ?>"
                            >Edit</button>
                            <button 
                                class="btn btn-sm btn-danger deleteBtn"
                                data-id="<?= $id ?>"
                                data-name="<?= htmlspecialchars($faculty['Last_Name']) ?>, <?= htmlspecialchars($faculty['First_Name']) ?>"
                            >Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- ADD MODAL FORM -->
<div class="modal fade" id="addFacultyModal" tabindex="-1" aria-labelledby="addFacultyLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="post" action="action_add.php">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="addFacultyLabel">New Faculty Entry</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          
          <div class="mb-3">
            <label for="id_no" class="form-label">ID No.</label>
            <input type="text" class="form-control" id="id_no" name="id_no" required>
          </div>

        
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="last_name" class="form-label">Last Name</label>
              <input type="text" class="form-control" id="last_name" name="last_name" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="first_name" class="form-label">First Name</label>
              <input type="text" class="form-control" id="first_name" name="first_name" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="middle_name" class="form-label">Middle Name</label>
              <input type="text" class="form-control" id="middle_name" name="middle_name">
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="gender" class="form-label">Gender</label>
              <select class="form-select" id="gender" name="gender" required>
                <option value="">-- Select Gender --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label for="faculty_rank" class="form-label">Faculty Rank</label>
              <select class="form-select" id="faculty_rank" name="faculty_rank" required>
                <option value="">-- Select Rank --</option>
                <option value="Permanent">Permanent</option>
                <option value="Temporary">Temporary</option>
                <option value="Part-Time">Part-Time</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label for="faculty_status" class="form-label">Faculty Status</label>
              <select class="form-select" id="faculty_status" name="faculty_status" required>
                <option value="">-- Select Status --</option>
                <option value="Core Faculty">Core Faculty</option>
                <option value="Adjunct Faculty">Adjunct Faculty</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label for="department" class="form-label">Department</label>
            <input type="text" class="form-control" id="department" name="department" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL FORM -->
<div class="modal fade" id="editFacultyModal" tabindex="-1" aria-labelledby="editFacultyLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="post" action="action_edit.php">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="editFacultyLabel">Edit Faculty Record</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">

          <div class="mb-3">
            <label>ID No.</label>
            <input type="text" class="form-control" name="id_no" id="edit_id_no" readonly>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Last Name</label>
              <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
            </div>
            <div class="col-md-4 mb-3">
              <label>First Name</label>
              <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
            </div>
            <div class="col-md-4 mb-3">
              <label>Middle Name</label>
              <input type="text" class="form-control" name="middle_name" id="edit_middle_name">
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Gender</label>
              <select class="form-select" name="gender" id="edit_gender" required>
                <option value="">-- Select Gender --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label>Faculty Rank</label>
              <select class="form-select" name="faculty_rank" id="edit_faculty_rank" required>
                <option value="Permanent">Permanent</option>
                <option value="Temporary">Temporary</option>
                <option value="Part-Time">Part-Time</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label>Faculty Status</label>
              <select class="form-select" name="faculty_status" id="edit_faculty_status" required>
                <option value="Core Faculty">Core Faculty</option>
                <option value="Adjunct Faculty">Adjunct Faculty</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label>Department</label>
            <input type="text" class="form-control" name="department" id="edit_department" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL CONFIRMATION -->
<div class="modal fade" id="deleteFacultyModal" tabindex="-1" aria-labelledby="deleteFacultyLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="get" action="delete.php">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteFacultyLabel">Delete Confirmation</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
          <input type="hidden" name="id" id="delete_id">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- VIEW MODAL -->
<div class="modal fade" id="viewFacultyModal" tabindex="-1" aria-labelledby="viewFacultyLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="viewFacultyLabel">View Faculty Record</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="view_id">

        <div class="mb-3">
          <label>ID No.</label>
          <input type="text" class="form-control" id="view_id_no" readonly>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Last Name</label>
            <input type="text" class="form-control" id="view_last_name" readonly>
          </div>
          <div class="col-md-4 mb-3">
            <label>First Name</label>
            <input type="text" class="form-control" id="view_first_name" readonly>
          </div>
          <div class="col-md-4 mb-3">
            <label>Middle Name</label>
            <input type="text" class="form-control" id="view_middle_name" readonly>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Gender</label>
            <input type="text" class="form-control" id="view_gender" readonly>
          </div>
          <div class="col-md-4 mb-3">
            <label>Faculty Rank</label>
            <input type="text" class="form-control" id="view_faculty_rank" readonly>
          </div>
          <div class="col-md-4 mb-3">
            <label>Faculty Status</label>
            <input type="text" class="form-control" id="view_faculty_status" readonly>
          </div>
        </div>

        <div class="mb-3">
          <label>Department</label>
          <input type="text" class="form-control" id="view_department" readonly>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<!-- SUCCESS MODAL -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         Faculty record has been successfully saved!
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#facultyTable').DataTable({
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50],
            order: [[0, 'asc']]
        });

        
        // VIEW MODAL
        $('.viewBtn').on('click', function () {
            $('#view_id').val($(this).data('id'));
            $('#view_id_no').val($(this).data('idno'));
            $('#view_last_name').val($(this).data('last'));
            $('#view_first_name').val($(this).data('first'));
            $('#view_middle_name').val($(this).data('middle'));
            $('#view_gender').val($(this).data('gender'));
            $('#view_faculty_rank').val($(this).data('rank'));
            $('#view_faculty_status').val($(this).data('status'));
            $('#view_department').val($(this).data('department'));

            $('#viewFacultyModal').modal('show');
        });



        $('.editBtn').on('click', function () {
            $('#edit_id').val($(this).data('id'));
            $('#edit_id_no').val($(this).data('idno'));
            $('#edit_last_name').val($(this).data('last'));
            $('#edit_first_name').val($(this).data('first'));
            $('#edit_middle_name').val($(this).data('middle'));
            $('#edit_gender').val($(this).data('gender'));
            $('#edit_faculty_rank').val($(this).data('rank'));
            $('#edit_faculty_status').val($(this).data('status'));
            $('#edit_department').val($(this).data('department'));
            $('#editFacultyModal').modal('show');
        });

        
        $('.deleteBtn').on('click', function () {
            $('#delete_id').val($(this).data('id'));
            $('#delete_name').text($(this).data('name'));
            $('#deleteFacultyModal').modal('show');
        });

       
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            showSuccessModal('Faculty record has been successfully saved!');
        <?php endif; ?>

        <?php if (isset($_GET['edit_success']) && $_GET['edit_success'] == 1): ?>
            showSuccessModal('Faculty record has been successfully updated!');
        <?php endif; ?>

        <?php if (isset($_GET['delete_success']) && $_GET['delete_success'] == 1): ?>
            showSuccessModal('Faculty record has been successfully deleted!');
        <?php endif; ?>

        
        function showSuccessModal(message) {
            $('#successModal .modal-body').text(message);
            const modal = new bootstrap.Modal(document.getElementById('successModal'));
            modal.show();

            document.getElementById('successModal').addEventListener('hidden.bs.modal', function () {
                window.location.href = "index.php";
            });
        }
    });
</script>
</body>
</html>


