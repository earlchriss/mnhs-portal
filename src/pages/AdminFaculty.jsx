import { useEffect, useMemo, useRef, useState } from "react";

const EMPTY_FORM = {
  employee_no: "",
  first_name: "",
  last_name: "",
  email: "",
  department: "",
  job_title: "",
  status: "active",
  teacher_id: "",
};

const STATUS_LABELS = { active: "Active", inactive: "Inactive" };

function ratingTone(avg) {
  if (avg >= 4) return "success";
  if (avg >= 3) return "warning";
  return "danger";
}

/* ---------- Modal shell: Esc to close, locks page scroll, returns focus ---------- */
function Modal({
  titleId,
  onClose,
  dismissOnBackdrop = false,
  size = "",
  children,
}) {
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
        onMouseDown={(event) => {
          if (dismissOnBackdrop && event.target === event.currentTarget)
            onClose();
        }}
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

function StatCard({ icon, tone, value, label, col }) {
  return (
    <div className={col}>
      <div className="card border-0 shadow-sm h-100">
        <div className="card-body d-flex align-items-center gap-3">
          <span
            className={`icon-box rounded-3 fs-5 bg-${tone}-subtle text-${tone}-emphasis`}
          >
            <i className={`fas ${icon}`} aria-hidden="true"></i>
          </span>
          <div>
            <div className="h4 fw-bold mb-0">{value}</div>
            <div className="small text-body-secondary">{label}</div>
          </div>
        </div>
      </div>
    </div>
  );
}

function Stars({ value }) {
  return (
    <span aria-hidden="true" className="text-warning text-nowrap">
      {[1, 2, 3, 4, 5].map((n) => (
        <i
          key={n}
          className={
            value >= n - 0.25
              ? "fas fa-star"
              : value >= n - 0.75
                ? "fas fa-star-half-stroke"
                : "far fa-star"
          }
        ></i>
      ))}
    </span>
  );
}

function ErrorList({ errors, heading }) {
  if (!errors.length) return null;
  return (
    <div className="alert alert-danger small" role="alert">
      <div className="fw-semibold mb-1">
        <i className="fas fa-circle-exclamation me-1" aria-hidden="true"></i>
        {heading}
      </div>
      <ul className="mb-0 ps-3">
        {errors.map((error) => (
          <li key={error}>{error}</li>
        ))}
      </ul>
    </div>
  );
}

/* ---------- Add / edit form (posts to faculty.php) ---------- */
function FacultyForm({
  mode,
  facultyId,
  initial,
  errors,
  csrf,
  statuses,
  teachers,
  faculty,
  onClose,
}) {
  const [form, setForm] = useState(initial);
  const [submitting, setSubmitting] = useState(false);
  const isEdit = mode === "edit";

  const set = (field) => (event) =>
    setForm((current) => ({ ...current, [field]: event.target.value }));

  // An LMS teacher can only be linked to one faculty member
  const linkedTo = (teacherId) =>
    faculty.find(
      (member) =>
        String(member.teacher_id) === String(teacherId) &&
        member.id !== facultyId,
    );

  return (
    <Modal titleId="facultyFormTitle" onClose={onClose} size="modal-lg">
      <form method="post" onSubmit={() => setSubmitting(true)}>
        <input type="hidden" name="csrf" value={csrf} />
        <input type="hidden" name="action" value={isEdit ? "edit" : "add"} />
        {isEdit && <input type="hidden" name="id" value={facultyId} />}

        <div className="modal-header">
          <h2 className="modal-title h5 fw-bold" id="facultyFormTitle">
            {isEdit ? "Edit faculty" : "Add faculty"}
          </h2>
          <button
            type="button"
            className="btn-close"
            aria-label="Close"
            onClick={onClose}
          ></button>
        </div>

        <div className="modal-body">
          <ErrorList errors={errors} heading="Could not save the faculty" />

          <div className="row g-3">
            <div className="col-md-6">
              <label htmlFor="firstName" className="form-label fw-semibold">
                First name
              </label>
              <input
                type="text"
                className="form-control"
                id="firstName"
                name="first_name"
                value={form.first_name}
                onChange={set("first_name")}
                maxLength={60}
                autoComplete="off"
                autoFocus
                required
              />
            </div>
            <div className="col-md-6">
              <label htmlFor="lastName" className="form-label fw-semibold">
                Last name
              </label>
              <input
                type="text"
                className="form-control"
                id="lastName"
                name="last_name"
                value={form.last_name}
                onChange={set("last_name")}
                maxLength={60}
                autoComplete="off"
                required
              />
            </div>
            <div className="col-md-6">
              <label htmlFor="employeeNo" className="form-label fw-semibold">
                Employee number{" "}
                <span className="fw-normal text-body-secondary">
                  (optional)
                </span>
              </label>
              <input
                type="text"
                className="form-control"
                id="employeeNo"
                name="employee_no"
                value={form.employee_no}
                onChange={set("employee_no")}
                maxLength={30}
                autoComplete="off"
              />
            </div>
            <div className="col-md-6">
              <label htmlFor="facultyEmail" className="form-label fw-semibold">
                Email{" "}
                <span className="fw-normal text-body-secondary">
                  (optional)
                </span>
              </label>
              <input
                type="email"
                className="form-control"
                id="facultyEmail"
                name="email"
                value={form.email}
                onChange={set("email")}
                maxLength={120}
                autoComplete="off"
              />
            </div>
            <div className="col-md-6">
              <label htmlFor="department" className="form-label fw-semibold">
                Department{" "}
                <span className="fw-normal text-body-secondary">
                  (optional)
                </span>
              </label>
              <input
                type="text"
                className="form-control"
                id="department"
                name="department"
                value={form.department}
                onChange={set("department")}
                maxLength={80}
                placeholder="e.g. Information Technology"
                autoComplete="off"
              />
            </div>
            <div className="col-md-6">
              <label htmlFor="jobTitle" className="form-label fw-semibold">
                Job title{" "}
                <span className="fw-normal text-body-secondary">
                  (optional)
                </span>
              </label>
              <input
                type="text"
                className="form-control"
                id="jobTitle"
                name="job_title"
                value={form.job_title}
                onChange={set("job_title")}
                maxLength={80}
                placeholder="e.g. Instructor I"
                autoComplete="off"
              />
            </div>
            <div className="col-md-4">
              <label htmlFor="facultyStatus" className="form-label fw-semibold">
                Status
              </label>
              <select
                className="form-select"
                id="facultyStatus"
                name="status"
                value={form.status}
                onChange={set("status")}
              >
                {statuses.map((status) => (
                  <option value={status} key={status}>
                    {STATUS_LABELS[status] || status}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-8">
              <label htmlFor="linkedTeacher" className="form-label fw-semibold">
                Linked LMS teacher{" "}
                <span className="fw-normal text-body-secondary">
                  (optional)
                </span>
              </label>
              <select
                className="form-select"
                id="linkedTeacher"
                name="teacher_id"
                value={String(form.teacher_id ?? "")}
                onChange={set("teacher_id")}
              >
                <option value="">Not linked</option>
                {Object.entries(teachers).map(([id, name]) => {
                  const other = linkedTo(id);
                  return (
                    <option value={id} key={id} disabled={Boolean(other)}>
                      {name}
                      {other ? ` (linked to ${other.name})` : ""}
                    </option>
                  );
                })}
              </select>
              <div className="form-text">
                Connects this person to their LMS account so schedules and
                evaluations match.
              </div>
            </div>
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
                <i className="fas fa-floppy-disk me-1" aria-hidden="true"></i>
                {isEdit ? "Save changes" : "Add faculty"}
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/* ---------- Ratings (read-only) ---------- */
function ViewRatings({ member, rating, filters, onClose, onEdit }) {
  const filtered = filters.term || filters.section;
  return (
    <Modal
      titleId="ratingsTitle"
      onClose={onClose}
      dismissOnBackdrop
      size="modal-lg"
    >
      <div className="modal-header">
        <div>
          <div className="small fw-semibold text-primary">
            {member.employee_no || "Faculty"}
          </div>
          <h2 className="modal-title h5 fw-bold" id="ratingsTitle">
            {member.name}
          </h2>
          <div className="small text-body-secondary">
            {[member.job_title, member.department]
              .filter(Boolean)
              .join(" · ") || "No title or department"}
          </div>
        </div>
        <button
          type="button"
          className="btn-close"
          aria-label="Close"
          onClick={onClose}
        ></button>
      </div>

      <div className="modal-body">
        {filtered && (
          <div className="alert alert-info small py-2">
            <i className="fas fa-filter me-1" aria-hidden="true"></i>
            Showing <strong>{filters.term || "all terms"}</strong>
            {" · "}
            <strong>{filters.section || "all sections"}</strong>
          </div>
        )}

        {rating ? (
          <>
            <div className="d-flex flex-wrap align-items-center gap-4 mb-4">
              <div>
                <div className="display-6 fw-bold lh-1">
                  {rating.avg.toFixed(2)}
                  <span className="fs-5 text-body-secondary"> / 5</span>
                </div>
                <Stars value={rating.avg} />
              </div>
              <div>
                <div className="h4 fw-bold mb-0">{rating.count}</div>
                <div className="small text-body-secondary">
                  {rating.count === 1 ? "response" : "responses"}
                </div>
              </div>
            </div>

            <div className="table-responsive">
              <table className="table table-sm align-middle mb-0">
                <thead>
                  <tr className="small text-body-secondary">
                    <th scope="col">Term</th>
                    <th scope="col">Section</th>
                    <th scope="col" className="text-end">
                      Responses
                    </th>
                    <th scope="col" className="text-end">
                      Average
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {rating.breakdown.map((row) => (
                    <tr key={`${row.term}|${row.section}`}>
                      <td>{row.term}</td>
                      <td>
                        <span className="badge bg-primary-subtle text-primary-emphasis">
                          {row.section}
                        </span>
                      </td>
                      <td className="text-end">{row.count}</td>
                      <td className="text-end">
                        <span
                          className={`badge bg-${ratingTone(row.avg)}-subtle text-${ratingTone(row.avg)}-emphasis`}
                        >
                          {row.avg.toFixed(2)}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        ) : (
          <div className="text-center text-body-secondary py-4">
            No evaluation responses{filtered ? " for this filter" : " yet"}.
          </div>
        )}
      </div>

      <div className="modal-footer">
        <button
          type="button"
          className="btn btn-outline-secondary"
          onClick={onClose}
        >
          Close
        </button>
        <button type="button" className="btn btn-primary" onClick={onEdit}>
          <i className="fas fa-pen me-1" aria-hidden="true"></i>Edit faculty
        </button>
      </div>
    </Modal>
  );
}

/* ---------- Delete confirmation ---------- */
function DeleteFaculty({ member, errors, csrf, onClose }) {
  const [submitting, setSubmitting] = useState(false);
  return (
    <Modal titleId="deleteTitle" onClose={onClose}>
      <form method="post" onSubmit={() => setSubmitting(true)}>
        <input type="hidden" name="csrf" value={csrf} />
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="id" value={member.id} />

        <div className="modal-header">
          <h2 className="modal-title h5 fw-bold" id="deleteTitle">
            Delete faculty
          </h2>
          <button
            type="button"
            className="btn-close"
            aria-label="Close"
            onClick={onClose}
          ></button>
        </div>
        <div className="modal-body">
          <ErrorList errors={errors} heading="Could not delete" />
          <p className="mb-0">
            Delete <strong>{member.name}</strong>? This cannot be undone.
          </p>
          <p className="small text-body-secondary mt-2 mb-0">
            A faculty member who already has evaluation responses cannot be
            deleted. Set their status to Inactive instead.
          </p>
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
            className="btn btn-danger"
            disabled={submitting}
          >
            <i className="fas fa-trash me-1" aria-hidden="true"></i>
            {submitting ? "Deleting..." : "Delete"}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/* ---------- Page ---------- */
export default function AdminFaculty({
  term,
  faculty,
  ratings,
  terms,
  sections,
  teachers,
  statuses,
  filters,
  flash,
  errors,
  deleteError,
  old,
  csrf,
}) {
  const [query, setQuery] = useState("");

  // After a failed save or delete, PHP sends the typed values back: reopen the same modal.
  const [modal, setModal] = useState(() => {
    if (deleteError.length) {
      const member = faculty.find((item) => item.id === old.id);
      return member ? { mode: "delete", member, errors: deleteError } : null;
    }
    if (errors.length) {
      return {
        mode: old.action === "edit" ? "edit" : "add",
        id: old.id,
        initial: { ...EMPTY_FORM, ...old },
        errors,
      };
    }
    return null;
  });

  const needle = query.trim().toLowerCase();
  const visible = useMemo(
    () =>
      faculty.filter((member) => {
        if (!needle) return true;
        return `${member.name} ${member.employee_no} ${member.department} ${member.job_title} ${member.email}`
          .toLowerCase()
          .includes(needle);
      }),
    [faculty, needle],
  );

  const ratingOf = (id) => (ratings && ratings[id]) || null;

  const stats = useMemo(() => {
    let responses = 0;
    let weighted = 0;
    let evaluated = 0;
    for (const member of faculty) {
      const rating = ratings && ratings[member.id];
      if (rating) {
        evaluated++;
        responses += rating.count;
        weighted += rating.avg * rating.count;
      }
    }
    return {
      total: faculty.length,
      active: faculty.filter((member) => member.status === "active").length,
      evaluated,
      responses,
      overall: responses ? weighted / responses : null,
    };
  }, [faculty, ratings]);

  const filterActive = Boolean(filters.term || filters.section);

  const openAdd = () =>
    setModal({ mode: "add", id: 0, initial: EMPTY_FORM, errors: [] });
  const openEdit = (member) =>
    setModal({
      mode: "edit",
      id: member.id,
      initial: {
        employee_no: member.employee_no,
        first_name: member.first_name,
        last_name: member.last_name,
        email: member.email,
        department: member.department,
        job_title: member.job_title,
        status: member.status,
        teacher_id: member.teacher_id,
      },
      errors: [],
    });
  const closeModal = () => setModal(null);

  return (
    <div className="container-xl">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">Faculty</h1>
          <p className="text-body-secondary mb-0">
            {term} &middot; Manage faculty and view their evaluation ratings.
          </p>
        </div>
        <button type="button" className="btn btn-primary" onClick={openAdd}>
          <i className="fas fa-plus me-1" aria-hidden="true"></i>Add faculty
        </button>
      </div>

      {flash && (
        <div
          className={`alert alert-${flash[0]} d-flex align-items-center gap-2`}
          role="status"
        >
          <i className="fas fa-circle-check" aria-hidden="true"></i>
          {flash[1]}
        </div>
      )}

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="row g-2 align-items-center mb-3">
            <div className="col-lg-4">
              <div className="input-group">
                <span className="input-group-text">
                  <i className="fas fa-search" aria-hidden="true"></i>
                </span>
                <input
                  type="search"
                  className="form-control"
                  placeholder="Search name, department or number"
                  aria-label="Search faculty"
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                />
              </div>
            </div>
            <div className="col-lg-8">
              <form
                method="get"
                className="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center"
              >
                <span className="small text-body-secondary">Ratings for</span>
                <select
                  className="form-select w-auto"
                  name="term"
                  defaultValue={filters.term}
                  aria-label="Filter ratings by term"
                  onChange={(event) => event.currentTarget.form.submit()}
                >
                  <option value="">All terms</option>
                  {terms.map((item) => (
                    <option value={item} key={item}>
                      {item}
                    </option>
                  ))}
                </select>
                <select
                  className="form-select w-auto"
                  name="section"
                  defaultValue={filters.section}
                  aria-label="Filter ratings by section"
                  onChange={(event) => event.currentTarget.form.submit()}
                >
                  <option value="">All sections</option>
                  {sections.map((item) => (
                    <option value={item} key={item}>
                      {item}
                    </option>
                  ))}
                </select>
                {filterActive && (
                  <a
                    className="btn btn-outline-secondary btn-sm"
                    href={window.location.pathname}
                  >
                    Clear
                  </a>
                )}
              </form>
            </div>
          </div>

          <div className="small text-body-secondary mb-2" aria-live="polite">
            Showing {visible.length} of {faculty.length}
          </div>

          {visible.length ? (
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr className="small text-body-secondary">
                    <th scope="col">Faculty</th>
                    <th scope="col">Department</th>
                    <th scope="col">Status</th>
                    <th scope="col">Rating</th>
                    <th scope="col" className="text-end">
                      <span className="visually-hidden">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {visible.map((member) => {
                    const rating = ratingOf(member.id);
                    return (
                      <tr key={member.id}>
                        <td>
                          <div className="fw-semibold">{member.name}</div>
                          <div className="small text-body-secondary">
                            {member.employee_no || "No employee number"}
                            {member.teacher_name && (
                              <span
                                className="ms-2 badge bg-secondary-subtle text-secondary-emphasis"
                                title="Linked LMS teacher"
                              >
                                <i
                                  className="fas fa-link me-1"
                                  aria-hidden="true"
                                ></i>
                                LMS
                              </span>
                            )}
                          </div>
                        </td>
                        <td>
                          <div>{member.department || "-"}</div>
                          {member.job_title && (
                            <div className="small text-body-secondary">
                              {member.job_title}
                            </div>
                          )}
                        </td>
                        <td>
                          <span
                            className={`badge ${
                              member.status === "active"
                                ? "bg-success-subtle text-success-emphasis"
                                : "bg-secondary-subtle text-secondary-emphasis"
                            }`}
                          >
                            {STATUS_LABELS[member.status] || member.status}
                          </span>
                        </td>
                        <td className="text-nowrap">
                          {rating ? (
                            <>
                              <span
                                className={`badge bg-${ratingTone(rating.avg)}-subtle text-${ratingTone(rating.avg)}-emphasis`}
                              >
                                <i
                                  className="fas fa-star me-1"
                                  aria-hidden="true"
                                ></i>
                                {rating.avg.toFixed(2)} / 5
                              </span>
                              <span className="small text-body-secondary ms-2">
                                {rating.count}{" "}
                                {rating.count === 1 ? "response" : "responses"}
                              </span>
                            </>
                          ) : (
                            <span className="small text-body-secondary">
                              No responses
                            </span>
                          )}
                        </td>
                        <td className="text-end text-nowrap">
                          <button
                            type="button"
                            className="btn btn-sm btn-outline-primary me-1"
                            onClick={() => setModal({ mode: "view", member })}
                            aria-label={`View ratings for ${member.name}`}
                          >
                            <i
                              className="fas fa-eye me-1"
                              aria-hidden="true"
                            ></i>
                            View
                          </button>
                          <button
                            type="button"
                            className="btn btn-sm btn-outline-primary me-1"
                            onClick={() => openEdit(member)}
                            aria-label={`Edit ${member.name}`}
                          >
                            <i
                              className="fas fa-pen me-1"
                              aria-hidden="true"
                            ></i>
                            Edit
                          </button>
                          <button
                            type="button"
                            className="btn btn-sm btn-outline-danger"
                            onClick={() =>
                              setModal({ mode: "delete", member, errors: [] })
                            }
                            aria-label={`Delete ${member.name}`}
                          >
                            <i className="fas fa-trash" aria-hidden="true"></i>
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center text-body-secondary py-5">
              {needle ? (
                <>
                  <p className="mb-3">No faculty match your search.</p>
                  <button
                    type="button"
                    className="btn btn-outline-secondary btn-sm"
                    onClick={() => setQuery("")}
                  >
                    Clear search
                  </button>
                </>
              ) : (
                <>
                  <p className="mb-3">There are no faculty yet.</p>
                  <button
                    type="button"
                    className="btn btn-primary btn-sm"
                    onClick={openAdd}
                  >
                    <i className="fas fa-plus me-1" aria-hidden="true"></i>Add
                    the first faculty member
                  </button>
                </>
              )}
            </div>
          )}
        </div>
      </div>

      {modal && modal.mode === "view" && (
        <ViewRatings
          member={modal.member}
          rating={ratingOf(modal.member.id)}
          filters={filters}
          onClose={closeModal}
          onEdit={() => openEdit(modal.member)}
        />
      )}
      {modal && modal.mode === "delete" && (
        <DeleteFaculty
          member={modal.member}
          errors={modal.errors}
          csrf={csrf}
          onClose={closeModal}
        />
      )}
      {modal && (modal.mode === "add" || modal.mode === "edit") && (
        <FacultyForm
          key={`${modal.mode}-${modal.id}`}
          mode={modal.mode}
          facultyId={modal.id}
          initial={{ ...EMPTY_FORM, ...modal.initial }}
          errors={modal.errors}
          csrf={csrf}
          statuses={statuses}
          teachers={teachers}
          faculty={faculty}
          onClose={closeModal}
        />
      )}
    </div>
  );
}
