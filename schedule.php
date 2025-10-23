<?php
include "config.php";
include "firebaseRDB.php";
session_start();
if (!isset($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');


$db = new firebaseRDB($databaseURL);

// Fetch faculty
$facultyRaw = $db->retrieve("faculty");
$faculty = json_decode($facultyRaw, true);
$faculty = is_array($faculty) ? $faculty : [];

// Fetch schedules
$schedulesRaw = $db->retrieve("schedules");
$schedules = json_decode($schedulesRaw, true);
$schedules = is_array($schedules) ? $schedules : [];

$events = [];

foreach ($schedules as $schedId => $sched) {
    if (empty($sched['days']) || empty($sched['time_from']) || empty($sched['time_to'])) {
        continue;
    }

    $facultyName = 'Unknown';
    if (isset($faculty[$sched['faculty_id']])) {
        $f = $faculty[$sched['faculty_id']];
        $facultyName = trim(($f['First_Name'] ?? '') . ' ' . ($f['Last_Name'] ?? ''));
        if ($facultyName === '') $facultyName = 'Unknown';
    }

    $days = array_map('trim', explode(',', $sched['days']));
    // handle month inputs (YYYY-MM)
    $startMonth = $sched['month_from'] . '-01';
    $endMonth   = $sched['month_to'] . '-01';

    // Create period from startMonth to endMonth (inclusive)
    try {
        $period = new DatePeriod(
            new DateTime($startMonth),
            new DateInterval('P1D'),
            (new DateTime($endMonth))->modify('first day of next month')
        );
    } catch (Exception $e) {
        continue; // skip invalid date ranges
    }

    foreach ($period as $date) {
        $dayName = $date->format('l');
        if (!in_array($dayName, $days, true)) continue;

        $startDateTime = $date->format('Y-m-d') . 'T' . $sched['time_from'];

        $startTime = strtotime($sched['time_from']);
        $endTime   = strtotime($sched['time_to']);

        // If end <= start, treat as overnight and push end to next day
        if ($endTime <= $startTime) {
            $endDate = clone $date;
            $endDate->modify('+1 day');
            $endDateTime = $endDate->format('Y-m-d') . 'T' . $sched['time_to'];
        } else {
            $endDateTime = $date->format('Y-m-d') . 'T' . $sched['time_to'];
        }

        $events[] = [
            'id' => (string)$schedId,
            'title' => $facultyName,
            'start' => $startDateTime,
            'end'   => $endDateTime,
            'extendedProps' => [
                'sched_key'    => $schedId,
                'faculty_id'   => $sched['faculty_id'] ?? '',
                'faculty_name' => $facultyName,
                'section'      => $sched['section'] ?? '',
                'subject_code' => $sched['subject_code'] ?? '',
                'room_mode'    => $sched['room_mode'] ?? '',
                'time_from'    => $sched['time_from'] ?? '',
                'time_to'      => $sched['time_to'] ?? '',
                'days'         => $sched['days'] ?? '',
                'meeting_link' => $sched['meeting_link'] ?? '',
                'month_from'   => $sched['month_from'] ?? '',
                'month_to'     => $sched['month_to'] ?? '',
                'learning_modality' => $sched['learning_modality'] ?? '',
                'is_weekly'    => $sched['is_weekly'] ?? '',
            ],
            'url' => !empty($sched['meeting_link']) ? $sched['meeting_link'] : '',
        ];
    }
}

$eventsJson = json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Schedule</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/main.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/list.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="top-header">
  <h2>Faculty Onsite Class Monitoring System</h2>
</div>

<div class="sidebar">
  <a href="index.php">Faculty List</a>
  <a href="#" class="active">Schedule</a>
  <a href="attendance.php">Attendance</a>
</div>

<div class="content">
  <h1>Schedule</h1>

  <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
    Add New Schedule
  </button>

  <div id="calendar" style="max-width: 1000px; height: 800px;"></div>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="post" action="action_add_schedule.php">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="addScheduleLabel">Add New Schedule</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="container-fluid">
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="faculty_id" class="form-label">Faculty</label>
                <select class="form-select" id="faculty_id" name="faculty_id" required>
                  <option value="">-- Select Faculty --</option>
                  <?php foreach ($faculty as $fid => $f): ?>
                    <option value="<?= htmlspecialchars($fid) ?>">
                      <?= htmlspecialchars($f['Last_Name'] ?? '') ?>, <?= htmlspecialchars($f['First_Name'] ?? '') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Days of the Week</label>
                <div class="dropdown w-100">
                  <button class="btn btn-outline-secondary dropdown-toggle w-100 d-flex justify-content-between align-items-center"
                          type="button" id="daysDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span id="daysSelectedText">Select day(s)</span>
                  </button>
                  <div class="dropdown-menu p-3 w-100" aria-labelledby="daysDropdown" style="max-height:250px;overflow-y:auto;">
                    <?php
                    $daysList = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                    foreach ($daysList as $d):
                      $id = substr($d, 0, 3);
                    ?>
                      <div class="form-check">
                        <input class="form-check-input day-check" type="checkbox" value="<?= $d ?>" id="d<?= $id ?>">
                        <label class="form-check-label" for="d<?= $id ?>"><?= $d ?></label>
                      </div>
                    <?php endforeach; ?>
                    <div class="mt-2">
                      <button type="button" class="btn btn-sm btn-primary w-100" id="applyDaysBtn">Apply</button>
                    </div>
                  </div>
                </div>
                <input type="hidden" name="days" id="daysHidden">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="section" class="form-label">Section</label>
                <input type="text" class="form-control" id="section" name="section" required>
              </div>
              <div class="col-md-6">
                <label for="month_from" class="form-label">Month From</label>
                <input type="month" class="form-control" id="month_from" name="month_from" required>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="subject_code" class="form-label">Subject Code</label>
                <input type="text" class="form-control" id="subject_code" name="subject_code" required>
              </div>
              <div class="col-md-6">
                <label for="month_to" class="form-label">Month To</label>
                <input type="month" class="form-control" id="month_to" name="month_to" required>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="room_mode" class="form-label">Room / Mode</label>
                <input type="text" class="form-control" id="room_mode" name="room_mode" required>
              </div>
              <div class="col-md-6">
                <label for="time_from" class="form-label">Time From</label>
                <input type="time" class="form-control" id="time_from" name="time_from" required>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="learning_modality" class="form-label">Learning Modality</label>
                <select class="form-select" id="learning_modality" name="learning_modality" required>
                  <option value="">-- Select Modality --</option>
                  <option value="F2F">Face-to-Face</option>
                  <option value="Online">Online Class</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="time_to" class="form-label">Time To</label>
                <input type="time" class="form-control" id="time_to" name="time_to" required>
              </div>
            </div>

            <div class="mb-3">
              <label for="meeting_link" class="form-label">Online Class Meeting Link</label>
              <input type="url" class="form-control" id="meeting_link" name="meeting_link" placeholder="https://...">
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="is_weekly" name="is_weekly" value="1">
              <label class="form-check-label" for="is_weekly">Make this a weekly schedule</label>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Schedule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View / Edit Modal -->
<div class="modal fade" id="viewEditScheduleModal" tabindex="-1" aria-labelledby="viewEditScheduleLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="updateScheduleForm">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title" id="viewEditScheduleLabel">Schedule Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="evt_id" name="id">

          <div class="mb-3">
            <label class="form-label">Faculty Name</label>
            <input type="text" id="evt_faculty_name" class="form-control" readonly>
          </div>

          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Section</label>
              <input type="text" id="evt_section" name="section" class="form-control" readonly>
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label">Subject Code</label>
              <input type="text" id="evt_subject_code" name="subject_code" class="form-control" readonly>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Room / Mode</label>
              <input type="text" id="evt_room_mode" name="room_mode" class="form-control" readonly>
            </div>
            <div class="col-md-3 mb-2">
              <label class="form-label">Start Time</label>
              <input type="time" id="evt_time_from" name="time_from" class="form-control" readonly>
            </div>
            <div class="col-md-3 mb-2">
              <label class="form-label">End Time</label>
              <input type="time" id="evt_time_to" name="time_to" class="form-control" readonly>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">Days</label>
            <input type="text" id="evt_days" name="days" class="form-control" placeholder="Monday, Tuesday" readonly>
          </div>

          <div class="mb-2">
            <label class="form-label">Meeting Link</label>
            <input type="url" id="evt_link" name="meeting_link" class="form-control" readonly>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" id="updateScheduleBtn" class="btn btn-primary">Update</button>
          <button type="button" id="deleteScheduleBtn" class="btn btn-danger">Delete</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Schedule Modal (editable; posts to action_update_schedule.php via AJAX) -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="editScheduleForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="editScheduleLabel">Edit Schedule</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="edit_id" name="id">

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="edit_faculty_id" class="form-label">Faculty</label>
              <select class="form-select" id="edit_faculty_id" name="faculty_id" required>
                <option value="">-- Select Faculty --</option>
                <?php foreach ($faculty as $fid => $f): ?>
                  <option value="<?= htmlspecialchars($fid) ?>"><?= htmlspecialchars($f['Last_Name'] ?? '') ?>, <?= htmlspecialchars($f['First_Name'] ?? '') ?></option>
                <?php endforeach; ?>
              </select>
            </div>

             <div class="col-md-6">
              <label class="form-label">Days of the Week</label>
              <div class="dropdown w-100">
                <button class="btn btn-outline-secondary dropdown-toggle w-100 d-flex justify-content-between align-items-center"
                        type="button" id="editDaysDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <span id="editDaysSelectedText">Select day(s)</span>
                </button>
                <div class="dropdown-menu p-3 w-100" aria-labelledby="editDaysDropdown" style="max-height:250px;overflow-y:auto;">
                  <?php
                  $daysList = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                  foreach ($daysList as $d):
                    $id = 'edit' . substr($d, 0, 3);
                  ?>
                    <div class="form-check">
                      <input class="form-check-input edit-day-check" type="checkbox" value="<?= $d ?>" id="<?= $id ?>">
                      <label class="form-check-label" for="<?= $id ?>"><?= $d ?></label>
                    </div>
                  <?php endforeach; ?>
                  <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-primary w-100" id="applyEditDaysBtn">Apply</button>
                  </div>
                </div>
              </div>
              <input type="hidden" name="days" id="editDaysHidden">
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="edit_section" class="form-label">Section</label>
              <input type="text" class="form-control" id="edit_section" name="section" required>
            </div>
            <div class="col-md-6">
              <label for="edit_month_from" class="form-label">Month From</label>
              <input type="month" class="form-control" id="edit_month_from" name="month_from" required>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="edit_subject_code" class="form-label">Subject Code</label>
              <input type="text" class="form-control" id="edit_subject_code" name="subject_code" required>
            </div>
            <div class="col-md-6">
              <label for="edit_month_to" class="form-label">Month To</label>
              <input type="month" class="form-control" id="edit_month_to" name="month_to" required>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="edit_room_mode" class="form-label">Room / Mode</label>
              <input type="text" class="form-control" id="edit_room_mode" name="room_mode" required>
            </div>
            <div class="col-md-6">
              <label for="edit_time_from" class="form-label">Time From</label>
              <input type="time" class="form-control" id="edit_time_from" name="time_from" required>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="edit_learning_modality" class="form-label">Learning Modality</label>
              <select class="form-select" id="edit_learning_modality" name="learning_modality" required>
                <option value="">-- Select Modality --</option>
                <option value="F2F">Face-to-Face</option>
                <option value="Online">Online Class</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="edit_time_to" class="form-label">Time To</label>
              <input type="time" class="form-control" id="edit_time_to" name="time_to" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="edit_meeting_link" class="form-label">Online Class Meeting Link</label>
            <input type="url" class="form-control" id="edit_meeting_link" name="meeting_link" placeholder="https://...">
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="edit_is_weekly" name="is_weekly" value="1">
            <label class="form-check-label" for="edit_is_weekly">Make this a weekly schedule</label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary text-white">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Success modal (optional) -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Schedule record has been successfully saved!
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteConfirmLabel">Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Are you sure you want to delete this schedule?</p>
        <p class="text-muted small mb-0">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/list.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Show success modal if requested
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    (function() {
      const modalEl = document.getElementById('successModal');
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
      modalEl.addEventListener('hidden.bs.modal', function () {
        window.history.replaceState({}, document.title, "schedule.php");
      });
    })();
  <?php endif; ?>

  // Days dropdown logic
  const dayChecks = document.querySelectorAll(".day-check");
  const daysHidden = document.getElementById("daysHidden");
  const daysSelectedText = document.getElementById("daysSelectedText");
  const applyDaysBtn = document.getElementById("applyDaysBtn");

  function updateDaysSelection(checkboxes, hidden, label) {
    const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
    hidden.value = selected.join(",");
    label.textContent = selected.length ? selected.join(", ") : "Select day(s)";
  }

  if (applyDaysBtn) {
    applyDaysBtn.addEventListener("click", () => {
      updateDaysSelection(dayChecks, daysHidden, daysSelectedText);
      const dd = bootstrap.Dropdown.getInstance(document.getElementById("daysDropdown"));
      dd && dd.hide();
    });
  }

  dayChecks.forEach(cb => cb.addEventListener("change", () => updateDaysSelection(dayChecks, daysHidden, daysSelectedText)));

  // Initialize FullCalendar
  const calendarEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
    },views: {
        listDay: {
          buttonText: 'List Day'
        }
      },
    events: <?= $eventsJson ?>,
    dayMaxEventRows: true,
    eventDisplay: 'block',
    eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      },
      eventDidMount: function(info) {
        if (info.view.type.startsWith('list')) {
          const titleEl = info.el.querySelector('.fc-event-title');
          if (titleEl) titleEl.textContent = info.event.title;
        }
      },
    eventClick: function(info) {
      info.jsEvent.preventDefault();
      const ev = info.event;
      const props = ev.extendedProps || {};

      // Populate view modal fields
      document.getElementById('evt_id').value = ev.id || '';
      document.getElementById('evt_faculty_name').value = props.faculty_name || ev.title || '';
      document.getElementById('evt_section').value = props.section || '';
      document.getElementById('evt_subject_code').value = props.subject_code || '';
      document.getElementById('evt_room_mode').value = props.room_mode || '';
      document.getElementById('evt_time_from').value = props.time_from || (ev.start ? ev.startStr.substring(11,16) : '');
      document.getElementById('evt_time_to').value = props.time_to || (ev.end ? ev.endStr.substring(11,16) : '');
      document.getElementById('evt_days').value = props.days || '';
      document.getElementById('evt_link').value = props.meeting_link || ev.url || '';

      new bootstrap.Modal(document.getElementById('viewEditScheduleModal')).show();
    }
  });

  calendar.render();

  const editDayChecks = document.querySelectorAll(".edit-day-check");
  const editDaysHidden = document.getElementById("editDaysHidden");
  const editDaysSelectedText = document.getElementById("editDaysSelectedText");
  const applyEditDaysBtn = document.getElementById("applyEditDaysBtn");

  function updateEditDaysSelection() {
    const selected = Array.from(editDayChecks).filter(cb => cb.checked).map(cb => cb.value);
    editDaysHidden.value = selected.join(",");
    editDaysSelectedText.textContent = selected.length ? selected.join(", ") : "Select day(s)";
  }

  if (applyEditDaysBtn) {
    applyEditDaysBtn.addEventListener("click", () => {
      updateEditDaysSelection();
      const dd = bootstrap.Dropdown.getInstance(document.getElementById("editDaysDropdown"));
      dd && dd.hide();
    });
  }

  editDayChecks.forEach(cb => cb.addEventListener("change", updateEditDaysSelection));


  // View modal update => open edit modal and populate
   function openEditModalFromEvent(ev) {
    const props = ev.extendedProps || {};
    
    // Populate basic fields
    document.getElementById('edit_id').value = ev.id || '';
    document.getElementById('edit_faculty_id').value = props.faculty_id || '';
    document.getElementById('edit_section').value = props.section || '';
    document.getElementById('edit_subject_code').value = props.subject_code || '';
    document.getElementById('edit_room_mode').value = props.room_mode || '';
    document.getElementById('edit_time_from').value = props.time_from || '';
    document.getElementById('edit_time_to').value = props.time_to || '';
    document.getElementById('edit_month_from').value = props.month_from || '';
    document.getElementById('edit_month_to').value = props.month_to || '';
    document.getElementById('edit_meeting_link').value = props.meeting_link || '';
    document.getElementById('edit_learning_modality').value = props.learning_modality || '';
    document.getElementById('edit_is_weekly').checked = !!props.is_weekly;

    // Handle days selection
    const daysString = props.days || '';
    const daysArray = daysString.split(',').map(d => d.trim()).filter(d => d);
    
    // Uncheck all first
    editDayChecks.forEach(cb => cb.checked = false);
    
    // Check the days from database
    editDayChecks.forEach(cb => {
      if (daysArray.includes(cb.value)) {
        cb.checked = true;
      }
    });
    
    // Update the display
    updateEditDaysSelection();
  }

  document.getElementById('updateScheduleBtn').addEventListener('click', function () {
    const id = document.getElementById('evt_id').value;
    if (!id) return;
    const ev = calendar.getEventById(id);
    if (!ev) return;

    openEditModalFromEvent(ev);
    const viewModalEl = document.getElementById('viewEditScheduleModal');
    const viewModal = bootstrap.Modal.getInstance(viewModalEl);
    viewModal && viewModal.hide();

    new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
  });

  // Submit edit form via AJAX
  document.getElementById('editScheduleForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('action_update_schedule.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(json => {
        if (json && json.success) {
          const editModalEl = document.getElementById('editScheduleModal');
          const editModal = bootstrap.Modal.getInstance(editModalEl);
          editModal && editModal.hide();
          location.reload();
        } else {
          alert((json && json.message) ? json.message : 'Update failed');
        }
      })
      .catch(() => alert('Network error'));
  });

  // Update form (from view modal) submit via AJAX
  const updateForm = document.getElementById('updateScheduleForm');
  const viewModalEl = document.getElementById('viewEditScheduleModal');
  const viewModal = new bootstrap.Modal(viewModalEl);

  updateForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(updateForm);

    fetch('action_update_schedule.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(json => {
        if (json && json.success) {
          viewModal.hide();
          
        } else {
          alert((json && json.message) ? json.message : 'Update failed');
        }
      })
      .catch(() => alert('Network error'));
  });

  // Delete
  // Delete - show confirmation modal
  document.getElementById('deleteScheduleBtn').addEventListener('click', function() {
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModal.show();
  });

  // Confirm delete button handler
  document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    const id = document.getElementById('evt_id').value;
    
    fetch('action_delete_schedule.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(json => {
      if (json && json.success) {
        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
        deleteModal && deleteModal.hide();
        viewModal.hide();
        location.reload();
      } else {
        alert((json && json.message) ? json.message : 'Delete failed');
      }
    })
    .catch(() => alert('Network error'));
  });

});
</script>
</body>

</html>

