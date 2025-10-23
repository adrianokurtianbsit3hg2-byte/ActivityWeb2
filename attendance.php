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
  <title>Attendance</title>
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
  <a href="schedule.php">Schedule</a>
  <a href="#" class="active">Attendance</a>
</div>

<div class="content">
  <h1>Attendance</h1>

  <div id="calendar" style="max-width: 1000px; height: 800px;"></div>
</div>

<!-- View Schedule Modal -->
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
                <label class="form-label">Date</label>
                <input type="text" id="evt_days" name="days" class="form-control" placeholder="Monday, Tuesday" readonly>
              </div>

              <div class="mb-2">
                <label class="form-label">Meeting Link</label>
                <input type="url" id="evt_link" name="meeting_link" class="form-control" readonly>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" id="facultyAttendanceBtn" class="btn btn-primary">Faculty Attendance</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
  </div>

<!-- Faculty Attendance Modal -->
<div class="modal fade" id="facultyAttendanceModal" tabindex="-1" aria-labelledby="facultyAttendanceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form id="facultyAttendanceForm" method="post" action="action_add_attendance.php">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="facultyAttendanceLabel">Faculty Attendance</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="att_sched_id" name="sched_id">

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Faculty Name</label>
                <input type="text" class="form-control" id="att_faculty_name" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Section</label>
                <input type="text" class="form-control" id="att_section" readonly>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Subject Code</label>
                <input type="text" class="form-control" id="att_subject_code" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Room / Mode</label>
                <input type="text" class="form-control" id="att_room_mode" readonly>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Time From</label>
                <input type="time" class="form-control" id="att_time_from" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Time To</label>
                <input type="time" class="form-control" id="att_time_to" readonly>
              </div>
            </div>

            <div class="row mb-3">
                  <div class="col-md-6">
                      <label class="form-label">Attendance Date</label>
                      <input type="text" class="form-control" id="att_date" name="attendance_date" readonly>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label">Learning Modality</label>
                      <input type="text" class="form-control" id="att_modality" readonly>
                  </div>
              </div>

            <div class="mb-3">
              <label class="form-label">Meeting Link</label>
              <div class="input-group">
                <input type="url" class="form-control" id="att_link" readonly>
                <a class="btn btn-outline-primary" target="_blank" id="att_link_btn">Go to Link</a>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                  <label class="form-label">Faculty Attendance</label>
                  <select class="form-select" id="att_status" name="attendance_status" required>
                  <option value="">-- Select Status --</option>
                  <option value="Present">Present</option>
                  <option value="Absent">Absent</option>
                  <option value="Excuse">Excuse</option>
                  <option value="Late">Late</option>
                  <option value="No Classes">No Classes</option>
                  </select>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Dress Code</label>
                  <select class="form-select" id="att_dress_code" name="dress_code" required>
                  <option value="">-- Select Dress Code --</option>
                  <option value="Filipiniana">Filipiniana</option>
                  <option value="ASEAN">ASEAN</option>
                  <option value="Casual wear">Casual wear</option>
                  <option value="N/A">N/A</option>
                  </select>
              </div>

            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" id="att_remarks" name="remarks" rows="2"></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Submit Attendance</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
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
        window.history.replaceState({}, document.title, "attendance.php");
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
    initialView: 'listWeek',
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
      const clickedDate = ev.start ? new Date(ev.start) : null;
        document.getElementById('evt_days').value = clickedDate
        ? clickedDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
        : '';
      document.getElementById('evt_link').value = props.meeting_link || ev.url || '';

      new bootstrap.Modal(document.getElementById('viewEditScheduleModal')).show();
    }
  });

  calendar.render();

   
  document.getElementById('facultyAttendanceBtn').addEventListener('click', function () {
  const id = document.getElementById('evt_id').value;
  if (!id) return;
  const ev = calendar.getEventById(id);
  if (!ev) return;
  const props = ev.extendedProps || {};

  document.getElementById('att_sched_id').value = id;
  document.getElementById('att_faculty_name').value = props.faculty_name || '';
  document.getElementById('att_section').value = props.section || '';
  document.getElementById('att_subject_code').value = props.subject_code || '';
  document.getElementById('att_room_mode').value = props.room_mode || '';
  document.getElementById('att_time_from').value = props.time_from || '';
  document.getElementById('att_time_to').value = props.time_to || '';
  document.getElementById('att_modality').value = props.learning_modality || '';
  document.getElementById('att_link').value = props.meeting_link || '';
  document.getElementById('att_link_btn').href = props.meeting_link || '#';
  document.getElementById('att_date').value = document.getElementById('evt_days').value;


  new bootstrap.Modal(document.getElementById('facultyAttendanceModal')).show();
});




  



  

});
</script>
</body>

</html>

