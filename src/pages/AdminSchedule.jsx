import { useEffect, useRef } from "react";

function formatTime(value) {
  const [hour, minute] = value.split(":").map(Number);
  const suffix = hour >= 12 ? "PM" : "AM";
  return `${hour % 12 || 12}:${String(minute).padStart(2, "0")} ${suffix}`;
}

// Short labels shown on screen. PHP and the database keep Mon, Tue, ... underneath.
const DAY_LABELS = {
  Mon: "M",
  Tue: "T",
  Wed: "W",
  Thu: "TH",
  Fri: "F",
  Sat: "SAT",
};
const dayLabel = (day) => DAY_LABELS[day] || day;

function SummaryCard({ icon, tone, value, label }) {
  return (
    <div className="col-6 col-lg-3">
      <div className="card border-0 shadow-sm h-100">
        <div className="card-body d-flex align-items-center gap-3">
          <span
            className={`icon-box rounded-3 fs-5 bg-${tone}-subtle text-${tone}-emphasis`}
          >
            <i className={`fas ${icon}`}></i>
          </span>
          <div>
            <div className="fs-4 fw-bold lh-1">{value}</div>
            <div className="small text-body-secondary">{label}</div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------- Bootstrap modal: add a section or a room (posts to schedule.php) ---------- */
function AddNameModal({
  id,
  title,
  label,
  placeholder,
  action,
  maxLength,
  csrf,
  errors,
  oldName,
}) {
  const modalRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => {
    const element = modalRef.current;
    if (!element) return;

    // Put the cursor in the field every time the modal finishes opening
    const focusInput = () => inputRef.current && inputRef.current.focus();
    element.addEventListener("shown.bs.modal", focusInput);

    // After a failed save PHP sends the errors back: reopen the modal to show them
    if (errors.length && window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(element).show();
    }

    return () => element.removeEventListener("shown.bs.modal", focusInput);
  }, []);

  return (
    <div
      className="modal fade"
      id={id}
      tabIndex="-1"
      aria-labelledby={`${id}Title`}
      aria-hidden="true"
      ref={modalRef}
    >
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content border-0 shadow">
          <form method="post">
            <input type="hidden" name="csrf" value={csrf} />
            <input type="hidden" name="action" value={action} />

            <div className="modal-header">
              <h2 className="modal-title h5 fw-bold" id={`${id}Title`}>
                {title}
              </h2>
              <button
                type="button"
                className="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"
              ></button>
            </div>

            <div className="modal-body">
              {errors.length > 0 && (
                <div className="alert alert-danger small" role="alert">
                  <div className="fw-semibold mb-1">
                    <i className="fas fa-circle-exclamation me-1"></i>
                    Could not save
                  </div>
                  <ul className="mb-0 ps-3">
                    {errors.map((error) => (
                      <li key={error}>{error}</li>
                    ))}
                  </ul>
                </div>
              )}
              <label htmlFor={`${id}Name`} className="form-label fw-semibold">
                {label}
              </label>
              <input
                type="text"
                className="form-control"
                id={`${id}Name`}
                name="name"
                defaultValue={oldName}
                maxLength={maxLength}
                placeholder={placeholder}
                autoComplete="off"
                required
                ref={inputRef}
              />
            </div>

            <div className="modal-footer">
              <button
                type="button"
                className="btn btn-outline-secondary"
                data-bs-dismiss="modal"
              >
                Cancel
              </button>
              <button type="submit" className="btn btn-primary">
                <i className="fas fa-floppy-disk me-1"></i>Save
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}

function AssignmentForm({
  subjects,
  teachers,
  sections,
  rooms,
  days,
  slots,
  old,
  errors,
  csrf,
}) {
  return (
    <section id="assign">
      <h2 className="h5 fw-bold mb-3">Assign a schedule</h2>
      <div className="card border-0 shadow-sm">
        <div className="card-body">
          {errors.length > 0 && (
            <div className="alert alert-danger small" role="alert">
              <div className="fw-semibold mb-1">
                <i className="fas fa-circle-exclamation me-1"></i>Could not
                assign this schedule
              </div>
              <ul className="mb-0 ps-3">
                {errors.map((error) => (
                  <li key={error}>{error}</li>
                ))}
              </ul>
            </div>
          )}
          <form method="post" noValidate>
            <input type="hidden" name="csrf" value={csrf} />
            <input type="hidden" name="action" value="assign" />
            <div className="mb-3">
              <label htmlFor="subject_id" className="form-label fw-semibold">
                Subject
              </label>
              <select
                className="form-select"
                id="subject_id"
                name="subject_id"
                defaultValue={old.subject_id || ""}
              >
                <option value="">Choose a subject...</option>
                {Object.entries(subjects).map(([id, subject]) => (
                  <option value={id} key={id}>
                    {subject.code} - {subject.title}
                  </option>
                ))}
              </select>
            </div>
            <div className="mb-3">
              <label htmlFor="teacher_id" className="form-label fw-semibold">
                Teacher
              </label>
              <select
                className="form-select"
                id="teacher_id"
                name="teacher_id"
                defaultValue={old.teacher_id || ""}
              >
                <option value="">Choose a teacher...</option>
                {Object.entries(teachers).map(([id, name]) => (
                  <option value={id} key={id}>
                    {name}
                  </option>
                ))}
              </select>
            </div>
            <div className="mb-3">
              <div className="d-flex align-items-center justify-content-between mb-2">
                <label
                  htmlFor="section"
                  className="form-label fw-semibold mb-0"
                >
                  Section (students)
                </label>
                <button
                  type="button"
                  className="btn btn-link btn-sm p-0 text-decoration-none"
                  data-bs-toggle="modal"
                  data-bs-target="#addSectionModal"
                >
                  <i className="fas fa-plus me-1"></i>Add section
                </button>
              </div>
              <select
                className="form-select"
                id="section"
                name="section"
                defaultValue={old.section || ""}
              >
                <option value="">Choose a section...</option>
                {sections.map((section) => (
                  <option key={section}>{section}</option>
                ))}
              </select>
            </div>
            <fieldset className="mb-3">
              <legend className="form-label fw-semibold fs-6">Days</legend>
              <div className="d-flex flex-wrap gap-2">
                {days.map((day) => (
                  <span key={day}>
                    <input
                      type="checkbox"
                      className="btn-check"
                      id={`day-${day}`}
                      name="days[]"
                      value={day}
                      defaultChecked={old.days.includes(day)}
                    />
                    <label
                      className="btn btn-outline-primary btn-sm"
                      htmlFor={`day-${day}`}
                    >
                      {dayLabel(day)}
                    </label>
                  </span>
                ))}
              </div>
            </fieldset>
            <div className="row g-2 mb-3">
              <div className="col-6">
                <label htmlFor="start" className="form-label fw-semibold">
                  Start
                </label>
                <select
                  className="form-select"
                  id="start"
                  name="start"
                  defaultValue={old.start || ""}
                >
                  <option value="">-</option>
                  {slots.map((slot) => (
                    <option value={slot} key={slot}>
                      {formatTime(slot)}
                    </option>
                  ))}
                </select>
              </div>
              <div className="col-6">
                <label htmlFor="end" className="form-label fw-semibold">
                  End
                </label>
                <select
                  className="form-select"
                  id="end"
                  name="end"
                  defaultValue={old.end || ""}
                >
                  <option value="">-</option>
                  {slots.map((slot) => (
                    <option value={slot} key={slot}>
                      {formatTime(slot)}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="mb-4">
              <div className="d-flex align-items-center justify-content-between mb-2">
                <label htmlFor="room" className="form-label fw-semibold mb-0">
                  Room
                </label>
                <button
                  type="button"
                  className="btn btn-link btn-sm p-0 text-decoration-none"
                  data-bs-toggle="modal"
                  data-bs-target="#addRoomModal"
                >
                  <i className="fas fa-plus me-1"></i>Add room
                </button>
              </div>
              <select
                className="form-select"
                id="room"
                name="room"
                defaultValue={old.room || ""}
              >
                <option value="">Choose a room...</option>
                {rooms.map((room) => (
                  <option key={room}>{room}</option>
                ))}
              </select>
            </div>
            <button type="submit" className="btn btn-primary w-100">
              <i className="fas fa-plus me-1"></i>Assign schedule
            </button>
            <div className="form-text mt-2">
              A schedule is rejected if the teacher, section or room is already
              booked at the same time.
            </div>
          </form>
        </div>
      </div>
    </section>
  );
}

function ScheduleViewer({
  shown,
  view,
  filter,
  sections,
  teachers,
  csrf,
  subjects,
}) {
  const filterOptions =
    view === "students"
      ? sections.map((section) => [section, section])
      : Object.entries(teachers);
  return (
    <section id="view">
      <h2 className="h5 fw-bold mb-3">Assigned schedules</h2>
      <div className="card border-0 shadow-sm">
        <div className="card-header bg-body py-3">
          <div className="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <ul className="nav nav-pills">
              <li className="nav-item">
                <a
                  className={`nav-link ${view === "students" ? "active" : ""}`}
                  href="?view=students"
                >
                  <i className="fas fa-user-graduate me-1"></i>Students
                </a>
              </li>
              <li className="nav-item">
                <a
                  className={`nav-link ${view === "teachers" ? "active" : ""}`}
                  href="?view=teachers"
                >
                  <i className="fas fa-chalkboard-user me-1"></i>Teachers
                </a>
              </li>
            </ul>
            <form method="get" className="d-flex">
              <input type="hidden" name="view" value={view} />
              <select
                className="form-select w-auto"
                name="filter"
                defaultValue={filter}
                aria-label="Filter schedules"
                onChange={(event) => event.currentTarget.form.submit()}
              >
                <option value="">All {view}</option>
                {filterOptions.map(([value, label]) => (
                  <option value={value} key={value}>
                    {label}
                  </option>
                ))}
              </select>
            </form>
          </div>
        </div>
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0 schedule-table">
            <thead className="table-light">
              <tr>
                <th>Subject</th>
                <th>{view === "students" ? "Teacher" : "Section"}</th>
                <th>Days</th>
                <th>Time</th>
                <th>Room</th>
                <th className="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              {shown.map((schedule) => {
                const subject = subjects[schedule.subject_id];
                return (
                  <tr key={schedule.id}>
                    <td>
                      <div className="fw-semibold">{subject.code}</div>
                      <div className="small text-body-secondary">
                        {subject.title}
                      </div>
                    </td>
                    <td className="text-nowrap">
                      {view === "students" ? (
                        teachers[schedule.teacher_id]
                      ) : (
                        <span className="badge bg-primary-subtle text-primary-emphasis">
                          {schedule.section}
                        </span>
                      )}
                    </td>
                    <td className="text-nowrap">
                      {schedule.days.map(dayLabel).join(" ")}
                    </td>
                    <td className="text-nowrap">
                      {formatTime(schedule.start)} - {formatTime(schedule.end)}
                    </td>
                    <td className="text-nowrap">{schedule.room}</td>
                    <td className="text-end">
                      <form
                        method="post"
                        className="d-inline"
                        onSubmit={(event) => {
                          if (!window.confirm("Remove this schedule?"))
                            event.preventDefault();
                        }}
                      >
                        <input type="hidden" name="csrf" value={csrf} />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="id" value={schedule.id} />
                        <button
                          type="submit"
                          className="btn btn-sm btn-outline-danger"
                          aria-label={`Remove schedule for ${subject.code}`}
                        >
                          <i className="fas fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                );
              })}
              {!shown.length && (
                <tr>
                  <td
                    colSpan="6"
                    className="text-center text-body-secondary py-4"
                  >
                    No schedules assigned yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </section>
  );
}

export default function AdminSchedule({
  term,
  days,
  slots,
  subjects,
  teachers,
  sections,
  rooms,
  shown,
  view,
  filter,
  flash,
  errors,
  old,
  csrf,
  sectionErrors = [],
  roomErrors = [],
  oldSectionName = "",
  oldRoomName = "",
  totalEntries,
  sectionsUsed,
  teachersUsed,
  weeklyHours,
}) {
  return (
    <div className="container-xl">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">Schedules</h1>
          <p className="text-body-secondary mb-0">{term}</p>
        </div>
      </div>
      {flash && (
        <div
          className={`alert alert-${flash[0]} d-flex align-items-center gap-2`}
          role="status"
        >
          <i className="fas fa-circle-check"></i>
          {flash[1]}
        </div>
      )}
      <div className="row g-4">
        <div className="col-lg-4">
          <AssignmentForm
            subjects={subjects}
            teachers={teachers}
            sections={sections}
            rooms={rooms}
            days={days}
            slots={slots}
            old={old}
            errors={errors}
            csrf={csrf}
          />
        </div>
        <div className="col-lg-8">
          <ScheduleViewer
            shown={shown}
            view={view}
            filter={filter}
            sections={sections}
            teachers={teachers}
            csrf={csrf}
            subjects={subjects}
          />
        </div>
      </div>

      <AddNameModal
        id="addSectionModal"
        title="Add section"
        label="Section name"
        placeholder="e.g. IT-3C"
        action="add_section"
        maxLength={50}
        csrf={csrf}
        errors={sectionErrors}
        oldName={oldSectionName}
      />
      <AddNameModal
        id="addRoomModal"
        title="Add room"
        label="Room name"
        placeholder="e.g. Room 301"
        action="add_room"
        maxLength={50}
        csrf={csrf}
        errors={roomErrors}
        oldName={oldRoomName}
      />
    </div>
  );
}
