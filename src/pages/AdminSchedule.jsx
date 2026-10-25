import { useEffect, useMemo, useRef, useState } from "react";

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

/* ---------- Modal shell: Esc to close, locks page scroll, returns focus ---------- */
function Modal({ titleId, onClose, size = "", children }) {
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  useEffect(() => {
    const opener = document.activeElement;
    const onKey = (event) => {
      if (event.key === "Escape") onCloseRef.current();
    };
    document.body.classList.add("modal-open");
    document.addEventListener("keydown", onKey);
    return () => {
      document.body.classList.remove("modal-open");
      document.removeEventListener("keydown", onKey);
      if (opener && typeof opener.focus === "function") opener.focus();
    };
  }, []);

  return (
    <>
      <div className="modal-backdrop fade show"></div>
      <div
        className="modal fade show d-block"
        tabIndex="-1"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <div
          className={`modal-dialog modal-dialog-centered modal-dialog-scrollable ${size}`}
        >
          <div className="modal-content border-0 shadow">{children}</div>
        </div>
      </div>
    </>
  );
}

const EMPTY_OLD = {
  subject_id: "",
  teacher_id: "",
  days: [],
  start: "",
  end: "",
  room: "",
};

/* ---------- Modal: add a subject to one section (posts to schedule.php) ---------- */
function AssignModal({
  section,
  subjects,
  teachers,
  rooms,
  days,
  slots,
  old,
  errors,
  csrf,
  onClose,
}) {
  const [submitting, setSubmitting] = useState(false);
  return (
    <Modal titleId="assignTitle" onClose={onClose} size="modal-lg">
      <form method="post" noValidate onSubmit={() => setSubmitting(true)}>
        <input type="hidden" name="csrf" value={csrf} />
        <input type="hidden" name="action" value="assign" />
        <input type="hidden" name="section" value={section} />

        <div className="modal-header">
          <div>
            <div className="small fw-semibold text-primary">{section}</div>
            <h2 className="modal-title h5 fw-bold" id="assignTitle">
              Add subject
            </h2>
          </div>
          <button
            type="button"
            className="btn-close"
            aria-label="Close"
            onClick={onClose}
          ></button>
        </div>

        <div className="modal-body">
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

          <div className="row g-3">
            <div className="col-12">
              <label htmlFor="subject_id" className="form-label fw-semibold">
                Subject
              </label>
              <select
                className="form-select"
                id="subject_id"
                name="subject_id"
                defaultValue={old.subject_id || ""}
                autoFocus
              >
                <option value="">Choose a subject...</option>
                {Object.entries(subjects).map(([id, subject]) => (
                  <option value={id} key={id}>
                    {subject.code} - {subject.title}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-12">
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
            <fieldset className="col-12">
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
            <div className="col-12">
              <label htmlFor="room" className="form-label fw-semibold">
                Room
              </label>
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
          </div>
          <div className="form-text mt-3">
            A schedule is rejected if the teacher, section or room is already
            booked at the same time.
          </div>
        </div>

        <div className="modal-footer">
          <button
            type="button"
            className="btn btn-outline-secondary"
            onClick={onClose}
          >
            Cancel
          </button>
          <button
            type="submit"
            className="btn btn-primary"
            disabled={submitting}
          >
            {submitting ? (
              <>
                <span
                  className="spinner-border spinner-border-sm me-2"
                  aria-hidden="true"
                ></span>
                Saving...
              </>
            ) : (
              <>
                <i className="fas fa-plus me-1"></i>Assign schedule
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/* ---------- Assign registered students to a section (posts to schedule.php) ---------- */
function StudentAssignment({
  students,
  studentSections,
  sections,
  errors,
  oldSection,
  oldIds,
  csrf,
}) {
  const [section, setSection] = useState(oldSection || "");
  const [checked, setChecked] = useState(() => new Set(oldIds));
  const [search, setSearch] = useState("");

  const needle = search.trim().toLowerCase();
  const visible = students.filter(
    (student) =>
      !needle ||
      `${student.name} ${student.email}`.toLowerCase().includes(needle),
  );

  const toggle = (id) =>
    setChecked((current) => {
      const next = new Set(current);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  const selectShown = () =>
    setChecked((current) => {
      const next = new Set(current);
      visible.forEach((student) => next.add(student.id));
      return next;
    });

  return (
    <section id="students">
      <h2 className="h5 fw-bold mb-3">Assign students to a section</h2>
      <div className="card border-0 shadow-sm">
        <div className="card-body">
          {errors.length > 0 && (
            <div className="alert alert-danger small" role="alert">
              <div className="fw-semibold mb-1">
                <i className="fas fa-circle-exclamation me-1"></i>Could not
                assign the students
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
            <input type="hidden" name="action" value="assign_students" />
            {[...checked].map((id) => (
              <input key={id} type="hidden" name="student_ids[]" value={id} />
            ))}

            <div className="mb-3">
              <label
                htmlFor="student_section"
                className="form-label fw-semibold"
              >
                Section
              </label>
              <select
                className="form-select"
                id="student_section"
                name="student_section"
                value={section}
                onChange={(event) => setSection(event.target.value)}
              >
                <option value="">Choose a section...</option>
                {sections.map((name) => (
                  <option key={name}>{name}</option>
                ))}
              </select>
            </div>

            <div className="d-flex align-items-center justify-content-between mb-2">
              <label
                htmlFor="student_search"
                className="form-label fw-semibold mb-0"
              >
                Registered students
              </label>
              <span className="small text-body-secondary">
                {checked.size} selected
              </span>
            </div>
            <input
              type="search"
              id="student_search"
              className="form-control form-control-sm mb-2"
              placeholder="Search name or email"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
            <div className="d-flex gap-3 mb-2 small">
              <button
                type="button"
                className="btn btn-link btn-sm p-0 text-decoration-none"
                onClick={selectShown}
              >
                Select shown
              </button>
              <button
                type="button"
                className="btn btn-link btn-sm p-0 text-decoration-none"
                onClick={() => setChecked(new Set())}
              >
                Clear
              </button>
            </div>

            <div
              className="list-group mb-3"
              style={{ maxHeight: "16rem", overflowY: "auto" }}
            >
              {visible.map((student) => {
                const current = studentSections[student.id];
                return (
                  <label
                    key={student.id}
                    className="list-group-item d-flex align-items-center gap-2"
                  >
                    <input
                      type="checkbox"
                      className="form-check-input mt-0"
                      checked={checked.has(student.id)}
                      onChange={() => toggle(student.id)}
                    />
                    <span className="flex-grow-1">
                      <span className="d-block fw-semibold small">
                        {student.name}
                      </span>
                      <span className="d-block small text-body-secondary">
                        {student.email}
                      </span>
                    </span>
                    {current ? (
                      <span className="badge bg-success-subtle text-success-emphasis">
                        {current}
                      </span>
                    ) : (
                      <span className="badge bg-secondary-subtle text-secondary-emphasis">
                        No section
                      </span>
                    )}
                  </label>
                );
              })}
              {!visible.length && (
                <div className="list-group-item text-center small text-body-secondary">
                  {students.length
                    ? "No students match your search."
                    : "No registered students yet."}
                </div>
              )}
            </div>

            <button type="submit" className="btn btn-primary w-100">
              <i className="fas fa-user-plus me-1"></i>Assign to section
            </button>
            <div className="form-text mt-2">
              Assigning a student again moves them to the new section.
            </div>
          </form>
        </div>
      </div>
    </section>
  );
}

function RemoveButton({ schedule, subject, csrf }) {
  return (
    <form
      method="post"
      className="d-inline"
      onSubmit={(event) => {
        if (!window.confirm("Remove this schedule?")) event.preventDefault();
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
  );
}

/* ---------- One section: its own schedule (Subject, Days, Time, Room) ---------- */
function SectionCard({
  name,
  entries,
  students,
  subjects,
  teachers,
  csrf,
  onAdd,
}) {
  return (
    <div className="card border-0 shadow-sm mb-4">
      <div className="card-header bg-body py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h3 className="h5 fw-bold mb-0">{name}</h3>
          <div className="small text-body-secondary">
            {entries.length} {entries.length === 1 ? "subject" : "subjects"}{" "}
            &middot; {students.length}{" "}
            {students.length === 1 ? "student" : "students"}
          </div>
        </div>
        <button
          type="button"
          className="btn btn-sm btn-outline-primary"
          onClick={() => onAdd(name)}
        >
          <i className="fas fa-plus me-1"></i>Add subject
        </button>
      </div>
      <div className="table-responsive">
        <table className="table table-hover align-middle mb-0 schedule-table">
          <thead className="table-light">
            <tr>
              <th>Subject</th>
              <th>Days</th>
              <th>Time</th>
              <th>Room</th>
              <th className="text-end">
                <span className="visually-hidden">Action</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {entries.map((schedule) => {
              const subject = subjects[schedule.subject_id] || {
                code: "?",
                title: "Unknown subject",
              };
              return (
                <tr key={schedule.id}>
                  <td>
                    <div className="fw-semibold">
                      {subject.code} - {subject.title}
                    </div>
                    <div className="small text-body-secondary">
                      {teachers[schedule.teacher_id]}
                    </div>
                  </td>
                  <td className="text-nowrap">
                    {schedule.days.map(dayLabel).join(" ")}
                  </td>
                  <td className="text-nowrap">
                    {formatTime(schedule.start)} - {formatTime(schedule.end)}
                  </td>
                  <td className="text-nowrap">{schedule.room}</td>
                  <td className="text-end">
                    <RemoveButton
                      schedule={schedule}
                      subject={subject}
                      csrf={csrf}
                    />
                  </td>
                </tr>
              );
            })}
            {!entries.length && (
              <tr>
                <td
                  colSpan="5"
                  className="text-center text-body-secondary py-4"
                >
                  No subjects scheduled yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      {students.length > 0 && (
        <div className="card-footer bg-body">
          <div className="small fw-semibold text-body-secondary mb-2">
            Students
          </div>
          <div className="d-flex flex-wrap gap-1">
            {students.map((student) => (
              <span
                key={student.id}
                className="badge bg-primary-subtle text-primary-emphasis fw-normal"
              >
                {student.name}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function ScheduleViewer({
  shown,
  filter,
  sections,
  teachers,
  csrf,
  subjects,
  studentsBySection,
  onAddToSection,
}) {
  // Every section gets its own card, even when it has no subjects yet.
  const cards = useMemo(
    () =>
      sections
        .filter((name) => filter === "" || name === filter)
        .slice()
        .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
        .map((name) => ({
          name,
          entries: shown.filter((schedule) => schedule.section === name),
        })),
    [sections, shown, filter],
  );

  return (
    <section id="view">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 className="h5 fw-bold mb-0">Schedule by section</h2>
        <form method="get" className="d-flex">
          <select
            className="form-select w-auto"
            name="filter"
            defaultValue={filter}
            aria-label="Filter by section"
            onChange={(event) => event.currentTarget.form.submit()}
          >
            <option value="">All sections</option>
            {sections.map((name) => (
              <option value={name} key={name}>
                {name}
              </option>
            ))}
          </select>
        </form>
      </div>

      {cards.length ? (
        cards.map((card) => (
          <SectionCard
            key={card.name}
            name={card.name}
            entries={card.entries}
            students={studentsBySection[card.name] || []}
            subjects={subjects}
            teachers={teachers}
            csrf={csrf}
            onAdd={onAddToSection}
          />
        ))
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="card-body text-center text-body-secondary py-5">
            No sections yet. Use "Add section" in the form to create one.
          </div>
        </div>
      )}
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
  students = [],
  studentSections = {},
  studentErrors = [],
  oldStudentSection = "",
  oldStudentIds = [],
}) {
  // The "Add subject" modal. After a failed save PHP sends the typed values back,
  // so it reopens with them (and the errors).
  const [assign, setAssign] = useState(() =>
    errors.length && old.section ? { section: old.section, old } : null,
  );
  const addToSection = (name) => setAssign({ section: name, old: EMPTY_OLD });

  // Students grouped by the section they are assigned to
  const studentsBySection = useMemo(() => {
    const groups = {};
    students.forEach((student) => {
      const name = studentSections[student.id];
      if (name) (groups[name] = groups[name] || []).push(student);
    });
    return groups;
  }, [students, studentSections]);

  return (
    <div className="container-xl">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">Schedules</h1>
          <p className="text-body-secondary mb-0">{term}</p>
        </div>
        <div className="d-flex flex-wrap gap-2">
          <button
            type="button"
            className="btn btn-outline-primary"
            data-bs-toggle="modal"
            data-bs-target="#addSectionModal"
          >
            <i className="fas fa-plus me-1"></i>Add section
          </button>
          <button
            type="button"
            className="btn btn-outline-primary"
            data-bs-toggle="modal"
            data-bs-target="#addRoomModal"
          >
            <i className="fas fa-plus me-1"></i>Add room
          </button>
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
          <StudentAssignment
            students={students}
            studentSections={studentSections}
            sections={sections}
            errors={studentErrors}
            oldSection={oldStudentSection}
            oldIds={oldStudentIds}
            csrf={csrf}
          />
        </div>
        <div className="col-lg-8">
          <ScheduleViewer
            shown={shown}
            filter={filter}
            sections={sections}
            teachers={teachers}
            csrf={csrf}
            subjects={subjects}
            studentsBySection={studentsBySection}
            onAddToSection={addToSection}
          />
        </div>
      </div>

      {assign && (
        <AssignModal
          key={assign.section}
          section={assign.section}
          subjects={subjects}
          teachers={teachers}
          rooms={rooms}
          days={days}
          slots={slots}
          old={assign.old}
          errors={assign.old === old ? errors : []}
          csrf={csrf}
          onClose={() => setAssign(null)}
        />
      )}

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
