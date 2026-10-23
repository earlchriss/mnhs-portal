import { useEffect, useMemo, useRef, useState } from "react";

const EMPTY_FORM = { code: "", title: "", description: "" };
const DESCRIPTION_MAX = 300;

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

function StatCard({ icon, value, label, col }) {
  return (
    <div className={col}>
      <div className="card border-0 shadow-sm h-100">
        <div className="card-body d-flex align-items-center gap-3">
          <span className="icon-box rounded-3 fs-5 bg-primary-subtle text-primary-emphasis">
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

/* ---------- View (read-only) ---------- */
function ViewSubject({ subject, scheduleKnown, onClose, onEdit }) {
  return (
    <Modal titleId="viewTitle" onClose={onClose} dismissOnBackdrop>
      <div className="modal-header">
        <div>
          <div className="small fw-semibold text-primary">{subject.code}</div>
          <h2 className="modal-title h5 fw-bold" id="viewTitle">
            {subject.title}
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
        <dl className="row mb-0 small">
          <dt className="col-sm-4 text-body-secondary fw-normal">Code</dt>
          <dd className="col-sm-8 fw-semibold">{subject.code}</dd>

          <dt className="col-sm-4 text-body-secondary fw-normal">
            Description
          </dt>
          <dd className="col-sm-8">
            {subject.description ? (
              subject.description
            ) : (
              <span className="text-body-secondary">No description.</span>
            )}
          </dd>

          {scheduleKnown && (
            <>
              <dt className="col-sm-4 text-body-secondary fw-normal">
                Scheduled in
              </dt>
              <dd className="col-sm-8 mb-0">
                {subject.sections.length ? (
                  <span className="d-flex flex-wrap gap-1">
                    {subject.sections.map((section) => (
                      <span
                        key={section}
                        className="badge bg-success-subtle text-success-emphasis"
                      >
                        {section}
                      </span>
                    ))}
                  </span>
                ) : (
                  <span className="text-body-secondary">
                    Not scheduled yet.{" "}
                    <a href="./schedule.php">Open schedule</a>
                  </span>
                )}
              </dd>
            </>
          )}
        </dl>
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
          <i className="fas fa-pen me-1" aria-hidden="true"></i>Edit subject
        </button>
      </div>
    </Modal>
  );
}

/* ---------- Add / edit form (posts to subjects.php) ---------- */
function SubjectForm({ mode, subjectId, initial, errors, csrf, onClose }) {
  const [form, setForm] = useState(initial);
  const [submitting, setSubmitting] = useState(false);
  const isEdit = mode === "edit";

  const set = (field) => (event) =>
    setForm((current) => ({ ...current, [field]: event.target.value }));

  return (
    <Modal titleId="formTitle" onClose={onClose}>
      <form method="post" onSubmit={() => setSubmitting(true)}>
        <input type="hidden" name="csrf" value={csrf} />
        <input type="hidden" name="action" value={isEdit ? "edit" : "add"} />
        {isEdit && <input type="hidden" name="id" value={subjectId} />}

        <div className="modal-header">
          <h2 className="modal-title h5 fw-bold" id="formTitle">
            {isEdit ? "Edit subject" : "Add subject"}
          </h2>
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
                <i
                  className="fas fa-circle-exclamation me-1"
                  aria-hidden="true"
                ></i>
                Could not save the subject
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
              <label htmlFor="subjectCode" className="form-label fw-semibold">
                Subject code
              </label>
              <input
                type="text"
                className="form-control"
                id="subjectCode"
                value={isEdit ? form.code : ""}
                placeholder="Random 5-digit code, created when saved"
                readOnly
                disabled
              />
              <div className="form-text">
                The code is generated automatically for LMS and cannot be
                changed.
              </div>
            </div>
            <div className="col-12">
              <label htmlFor="subjectTitle" className="form-label fw-semibold">
                Subject title
              </label>
              <input
                type="text"
                className="form-control"
                id="subjectTitle"
                name="title"
                value={form.title}
                onChange={set("title")}
                maxLength={120}
                placeholder="e.g. Systems Administration"
                autoComplete="off"
                autoFocus
                required
              />
            </div>
            <div className="col-12">
              <label
                htmlFor="subjectDescription"
                className="form-label fw-semibold"
              >
                Description{" "}
                <span className="fw-normal text-body-secondary">
                  (optional)
                </span>
              </label>
              <textarea
                className="form-control"
                id="subjectDescription"
                name="description"
                rows="3"
                value={form.description}
                onChange={set("description")}
                maxLength={DESCRIPTION_MAX}
              ></textarea>
              <div className="form-text text-end">
                {form.description.length}/{DESCRIPTION_MAX}
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
                {isEdit ? "Save changes" : "Add subject"}
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/* ---------- Page ---------- */
export default function AdminSubjects({
  term,
  subjects,
  scheduleKnown,
  flash,
  errors,
  old,
  csrf,
}) {
  const [query, setQuery] = useState("");
  // After a failed save, PHP sends the typed values back: reopen the form with them.
  const [modal, setModal] = useState(() =>
    errors.length
      ? {
          mode: old.action === "edit" ? "edit" : "add",
          id: old.id,
          initial: old,
          errors,
        }
      : null,
  );

  const needle = query.trim().toLowerCase();
  const visible = useMemo(
    () =>
      subjects.filter((subject) => {
        if (!needle) return true;
        return `${subject.code} ${subject.title} ${subject.description}`
          .toLowerCase()
          .includes(needle);
      }),
    [subjects, needle],
  );

  const scheduledCount = subjects.filter(
    (subject) => subject.sections.length > 0,
  ).length;
  const statCol = scheduleKnown ? "col-6" : "col-12 col-sm-6 col-lg-4";

  const openAdd = () =>
    setModal({ mode: "add", id: 0, initial: EMPTY_FORM, errors: [] });
  const openEdit = (subject) =>
    setModal({
      mode: "edit",
      id: subject.id,
      initial: {
        code: subject.code,
        title: subject.title,
        description: subject.description,
      },
      errors: [],
    });
  const closeModal = () => setModal(null);

  return (
    <div className="container-xl">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">Subjects</h1>
          <p className="text-body-secondary mb-0">
            {term} &middot; Add, edit and view every subject offered.
          </p>
        </div>
        <button type="button" className="btn btn-primary" onClick={openAdd}>
          <i className="fas fa-plus me-1" aria-hidden="true"></i>Add subject
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
            <div className="col-md-8 col-lg-6">
              <div className="input-group">
                <span className="input-group-text">
                  <i className="fas fa-search" aria-hidden="true"></i>
                </span>
                <input
                  type="search"
                  className="form-control"
                  placeholder="Search by code or title"
                  aria-label="Search subjects"
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                />
              </div>
            </div>
            <div
              className="col-md-4 col-lg-6 text-md-end small text-body-secondary"
              aria-live="polite"
            >
              Showing {visible.length} of {subjects.length}
            </div>
          </div>

          {visible.length ? (
            <div className="table-responsive position-relative">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr className="small text-body-secondary">
                    <th scope="col">Code</th>
                    <th scope="col">Subject</th>
                    {scheduleKnown && <th scope="col">Scheduled in</th>}
                    <th scope="col" className="text-end">
                      <span className="visually-hidden">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {visible.map((subject) => (
                    <tr key={subject.id}>
                      <td className="fw-semibold text-primary text-nowrap">
                        {subject.code}
                      </td>
                      <td>
                        <div className="fw-semibold">{subject.title}</div>
                        {subject.description && (
                          <div
                            className="small text-body-secondary text-truncate"
                            style={{ maxWidth: "28rem" }}
                          >
                            {subject.description}
                          </div>
                        )}
                      </td>
                      {scheduleKnown && (
                        <td>
                          {subject.sections.length ? (
                            <span className="d-flex flex-wrap gap-1">
                              {subject.sections.map((section) => (
                                <span
                                  key={section}
                                  className="badge bg-success-subtle text-success-emphasis"
                                >
                                  {section}
                                </span>
                              ))}
                            </span>
                          ) : (
                            <span className="badge bg-secondary-subtle text-secondary-emphasis">
                              Not scheduled
                            </span>
                          )}
                        </td>
                      )}
                      <td className="text-end text-nowrap">
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-primary me-1"
                          onClick={() => setModal({ mode: "view", subject })}
                          aria-label={`View ${subject.code}`}
                        >
                          <i className="fas fa-eye me-1" aria-hidden="true"></i>
                          View
                        </button>
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-primary"
                          onClick={() => openEdit(subject)}
                          aria-label={`Edit ${subject.code}`}
                        >
                          <i className="fas fa-pen me-1" aria-hidden="true"></i>
                          Edit
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center text-body-secondary py-5">
              {needle ? (
                <>
                  <p className="mb-3">No subjects match your search.</p>
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
                  <p className="mb-3">There are no subjects yet.</p>
                  <button
                    type="button"
                    className="btn btn-primary btn-sm"
                    onClick={openAdd}
                  >
                    <i className="fas fa-plus me-1" aria-hidden="true"></i>Add
                    the first subject
                  </button>
                </>
              )}
            </div>
          )}
        </div>
      </div>

      {modal && modal.mode === "view" && (
        <ViewSubject
          subject={modal.subject}
          scheduleKnown={scheduleKnown}
          onClose={closeModal}
          onEdit={() => openEdit(modal.subject)}
        />
      )}
      {modal && modal.mode !== "view" && (
        <SubjectForm
          key={`${modal.mode}-${modal.id}`}
          mode={modal.mode}
          subjectId={modal.id}
          initial={{ ...EMPTY_FORM, ...modal.initial }}
          errors={modal.errors}
          csrf={csrf}
          onClose={closeModal}
        />
      )}
    </div>
  );
}
