import { useEffect, useMemo, useRef, useState } from "react";

const EMPTY_FORM = {
  username: "",
  firstname: "",
  lastname: "",
  email: "",
  idnumber: "",
  role: "student",
  password: "",
  password_confirm: "",
};

const ROLE_TONE = { student: "primary", teacher: "success", admin: "danger" };

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

function StatCard({ icon, value, label }) {
  return (
    <div className="col-6 col-lg-3">
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

function ErrorList({ title, errors }) {
  if (!errors.length) return null;
  return (
    <div className="alert alert-danger small" role="alert">
      <div className="fw-semibold mb-1">
        <i className="fas fa-circle-exclamation me-1" aria-hidden="true"></i>
        {title}
      </div>
      <ul className="mb-0 ps-3">
        {errors.map((error) => (
          <li key={error}>{error}</li>
        ))}
      </ul>
    </div>
  );
}

/* ---------- Add / edit form (posts to users.php) ---------- */
function UserForm({ mode, userId, initial, errors, roles, csrf, onClose }) {
  const [form, setForm] = useState(initial);
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const isEdit = mode === "edit";

  const set = (field) => (event) =>
    setForm((current) => ({ ...current, [field]: event.target.value }));

  return (
    <Modal titleId="userFormTitle" onClose={onClose}>
      <form
        method="post"
        autoComplete="off"
        onSubmit={() => setSubmitting(true)}
      >
        <input type="hidden" name="csrf" value={csrf} />
        <input type="hidden" name="action" value={isEdit ? "edit" : "add"} />
        {isEdit && <input type="hidden" name="id" value={userId} />}

        <div className="modal-header">
          <h2 className="modal-title h5 fw-bold" id="userFormTitle">
            {isEdit ? "Edit user" : "Add user"}
          </h2>
          <button
            type="button"
            className="btn-close"
            aria-label="Close"
            onClick={onClose}
          ></button>
        </div>

        <div className="modal-body">
          <ErrorList title="Could not save the user" errors={errors} />
          {!isEdit && (
            <p className="small text-body-secondary">
              <i className="fas fa-link me-1" aria-hidden="true"></i>
              Saving also creates this account in LMS.
            </p>
          )}

          <div className="row g-3">
            <div className="col-sm-6">
              <label htmlFor="userFirst" className="form-label fw-semibold">
                First name
              </label>
              <input
                type="text"
                className="form-control"
                id="userFirst"
                name="firstname"
                value={form.firstname}
                onChange={set("firstname")}
                maxLength={60}
                autoFocus
                required
              />
            </div>
            <div className="col-sm-6">
              <label htmlFor="userLast" className="form-label fw-semibold">
                Last name
              </label>
              <input
                type="text"
                className="form-control"
                id="userLast"
                name="lastname"
                value={form.lastname}
                onChange={set("lastname")}
                maxLength={60}
                required
              />
            </div>
            <div className="col-sm-6">
              <label htmlFor="userName" className="form-label fw-semibold">
                Username
              </label>
              <input
                type="text"
                className="form-control"
                id="userName"
                name="username"
                value={form.username}
                onChange={set("username")}
                maxLength={50}
                autoCapitalize="none"
                spellCheck="false"
                required
              />
              <div className="form-text">Lowercase. Used to sign in.</div>
            </div>
            <div className="col-sm-6">
              <label htmlFor="userRole" className="form-label fw-semibold">
                Role
              </label>
              <select
                className="form-select"
                id="userRole"
                name="role"
                value={form.role}
                onChange={set("role")}
                required
              >
                {Object.entries(roles).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-12">
              <label htmlFor="userEmail" className="form-label fw-semibold">
                Email
              </label>
              <input
                type="email"
                className="form-control"
                id="userEmail"
                name="email"
                value={form.email}
                onChange={set("email")}
                maxLength={100}
                required
              />
            </div>
            <div className="col-12">
              <label htmlFor="userIdNumber" className="form-label fw-semibold">
                ID number{" "}
                <span className="fw-normal text-body-secondary">
                  (optional)
                </span>
              </label>
              <input
                type="text"
                className="form-control"
                id="userIdNumber"
                name="idnumber"
                value={form.idnumber}
                onChange={set("idnumber")}
                maxLength={50}
                placeholder="e.g. 2023-01234"
              />
            </div>
            <div className="col-sm-6">
              <label htmlFor="userPass" className="form-label fw-semibold">
                {isEdit ? "New password" : "Password"}
              </label>
              <div className="input-group">
                <input
                  type={showPassword ? "text" : "password"}
                  className="form-control"
                  id="userPass"
                  name="password"
                  value={form.password}
                  onChange={set("password")}
                  autoComplete="new-password"
                  required={!isEdit}
                />
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? "Hide password" : "Show password"}
                >
                  <i
                    className={`fas ${showPassword ? "fa-eye-slash" : "fa-eye"}`}
                    aria-hidden="true"
                  ></i>
                </button>
              </div>
              <div className="form-text">
                {isEdit
                  ? "Leave blank to keep the current one."
                  : "At least 8 characters."}
              </div>
            </div>
            <div className="col-sm-6">
              <label htmlFor="userPass2" className="form-label fw-semibold">
                Confirm password
              </label>
              <input
                type={showPassword ? "text" : "password"}
                className="form-control"
                id="userPass2"
                name="password_confirm"
                value={form.password_confirm}
                onChange={set("password_confirm")}
                autoComplete="new-password"
                required={!isEdit}
              />
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
                {isEdit ? "Saving..." : "Creating in LMS..."}
              </>
            ) : (
              <>
                <i className="fas fa-floppy-disk me-1" aria-hidden="true"></i>
                {isEdit ? "Save changes" : "Add user"}
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/* ---------- Delete confirmation ---------- */
function DeleteUser({ user, csrf, errors, onClose }) {
  const [submitting, setSubmitting] = useState(false);
  return (
    <Modal titleId="userDeleteTitle" onClose={onClose}>
      <form method="post" onSubmit={() => setSubmitting(true)}>
        <input type="hidden" name="csrf" value={csrf} />
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="id" value={user.id} />
        <div className="modal-header">
          <h2 className="modal-title h5 fw-bold" id="userDeleteTitle">
            Delete this user?
          </h2>
          <button
            type="button"
            className="btn-close"
            aria-label="Close"
            onClick={onClose}
          ></button>
        </div>
        <div className="modal-body">
          <ErrorList title="Could not delete the user" errors={errors} />
          <p className="mb-2">
            <span className="fw-semibold">
              {user.firstname} {user.lastname}
            </span>{" "}
            <span className="text-body-secondary">({user.username})</span>
          </p>
          <p className="small text-body-secondary mb-0">
            This also deletes the LMS account, including their enrolments,
            grades and submissions. It cannot be undone.
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
            {submitting ? (
              <>
                <span
                  className="spinner-border spinner-border-sm me-2"
                  aria-hidden="true"
                ></span>
                Deleting...
              </>
            ) : (
              <>
                <i className="fas fa-trash me-1" aria-hidden="true"></i>Delete
                user
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

/* ---------- Bulk import ---------- */
const TEMPLATE_CSV =
  "username,firstname,lastname,email,role,idnumber,password\r\n" +
  "juan.delacruz,Juan,Dela Cruz,juan@example.com,student,2024-00001,\r\n" +
  "maria.santos,Maria,Santos,maria@example.com,teacher,,\r\n";

// A cell starting with = + - @ would run as a formula when opened in Excel.
const safeCell = (value) => {
  const text = String(value ?? "");
  const guarded = /^[=+\-@\t\r]/.test(text) ? `'${text}` : text;
  return /[",\r\n]/.test(guarded)
    ? `"${guarded.replace(/"/g, '""')}"`
    : guarded;
};

function downloadCsv(filename, text) {
  const blob = new Blob(["﻿" + text], { type: "text/csv;charset=utf-8" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(link.href), 1000);
}

function ImportForm({ errors, csrf, max, onClose }) {
  const [fileName, setFileName] = useState("");
  const [submitting, setSubmitting] = useState(false);
  return (
    <Modal titleId="importTitle" onClose={onClose}>
      <form
        method="post"
        encType="multipart/form-data"
        onSubmit={() => setSubmitting(true)}
      >
        <input type="hidden" name="csrf" value={csrf} />
        <input type="hidden" name="action" value="import" />
        <div className="modal-header">
          <h2 className="modal-title h5 fw-bold" id="importTitle">
            Import users from CSV
          </h2>
          <button
            type="button"
            className="btn-close"
            aria-label="Close"
            onClick={onClose}
          ></button>
        </div>
        <div className="modal-body">
          <ErrorList title="Could not import the file" errors={errors} />
          <p className="small text-body-secondary">
            Each row creates an account in the project and in the LMS. Rows that
            fail are skipped and listed afterwards; the rest are still created.
            Up to {max} users per file.
          </p>
          <ul className="small ps-3">
            <li>
              Required columns: <code>username</code>, <code>firstname</code>,{" "}
              <code>lastname</code>, <code>email</code>.
            </li>
            <li>
              Optional: <code>role</code> (student, teacher or admin; blank
              means student), <code>idnumber</code>, <code>password</code>.
            </li>
            <li>
              A blank password gets a random one, shown once when the import
              finishes.
            </li>
          </ul>
          <button
            type="button"
            className="btn btn-sm btn-outline-secondary mb-3"
            onClick={() => downloadCsv("users-template.csv", TEMPLATE_CSV)}
          >
            <i className="fas fa-download me-1" aria-hidden="true"></i>Download
            template
          </button>
          <div>
            <label htmlFor="importFile" className="form-label fw-semibold">
              CSV file
            </label>
            <input
              type="file"
              className="form-control"
              id="importFile"
              name="csv"
              accept=".csv,.txt,text/csv"
              onChange={(event) =>
                setFileName(event.target.files[0]?.name || "")
              }
              required
            />
            <div className="form-text">
              In Excel, save as <em>CSV UTF-8</em>. Maximum 1 MB.
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
            disabled={submitting || !fileName}
          >
            {submitting ? (
              <>
                <span
                  className="spinner-border spinner-border-sm me-2"
                  aria-hidden="true"
                ></span>
                Importing... this can take a minute
              </>
            ) : (
              <>
                <i className="fas fa-file-import me-1" aria-hidden="true"></i>
                Import users
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

function ImportResult({ result, onClose }) {
  const hasPasswords = result.rows.some((row) => row.password);
  const download = () => {
    const header = [
      "row",
      "username",
      "name",
      "status",
      "message",
      "generated password",
    ];
    const lines = [header.join(",")].concat(
      result.rows.map((row) =>
        [
          row.line,
          row.username,
          row.name,
          row.status,
          row.message,
          row.password,
        ]
          .map(safeCell)
          .join(","),
      ),
    );
    downloadCsv("import-results.csv", lines.join("\r\n") + "\r\n");
  };
  return (
    <Modal titleId="importResultTitle" onClose={onClose} size="modal-lg">
      <div className="modal-header">
        <h2 className="modal-title h5 fw-bold" id="importResultTitle">
          Import finished
        </h2>
        <button
          type="button"
          className="btn-close"
          aria-label="Close"
          onClick={onClose}
        ></button>
      </div>
      <div className="modal-body">
        <div className="d-flex flex-wrap gap-2 mb-3">
          <span className="badge bg-success-subtle text-success-emphasis fs-6">
            {result.created} created
          </span>
          {result.failed > 0 && (
            <span className="badge bg-danger-subtle text-danger-emphasis fs-6">
              {result.failed} not created
            </span>
          )}
          <span className="badge bg-secondary-subtle text-secondary-emphasis fs-6">
            {result.total} rows
          </span>
        </div>
        {hasPasswords && (
          <div className="alert alert-warning small" role="alert">
            <i className="fas fa-key me-1" aria-hidden="true"></i>
            Generated passwords are shown <strong>only now</strong>. Download
            the results file and share each password privately. They cannot be
            shown again.
          </div>
        )}
        <div className="table-responsive position-relative">
          <table className="table table-sm align-middle mb-0">
            <thead>
              <tr className="small text-body-secondary">
                <th scope="col">Row</th>
                <th scope="col">User</th>
                <th scope="col">Result</th>
                {hasPasswords && <th scope="col">Password</th>}
              </tr>
            </thead>
            <tbody>
              {result.rows.map((row) => (
                <tr key={row.line}>
                  <td>{row.line}</td>
                  <td>
                    <div className="fw-semibold">{row.name || "—"}</div>
                    <div className="small text-body-secondary">
                      {row.username}
                    </div>
                  </td>
                  <td className="small">
                    {row.status === "created" ? (
                      <span className="badge bg-success-subtle text-success-emphasis">
                        Created
                      </span>
                    ) : (
                      <span className="text-danger">{row.message}</span>
                    )}
                  </td>
                  {hasPasswords && (
                    <td>
                      <code>{row.password}</code>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      <div className="modal-footer">
        <button
          type="button"
          className="btn btn-outline-primary"
          onClick={download}
        >
          <i className="fas fa-download me-1" aria-hidden="true"></i>Download
          results (CSV)
        </button>
        <button type="button" className="btn btn-primary" onClick={onClose}>
          Close
        </button>
      </div>
    </Modal>
  );
}

/* ---------- Page ---------- */
export default function AdminUsers({
  users,
  roles,
  moodleReady,
  moodleMessage,
  flash,
  errors,
  deleteError,
  old,
  importResult,
  importMax,
  csrf,
}) {
  const [query, setQuery] = useState("");
  const [role, setRole] = useState("");
  // After a failed save, PHP sends the typed values back: reopen the dialog.
  const [modal, setModal] = useState(() => {
    if (importResult) return { mode: "importresult" };
    if (!errors.length) return null;
    if (old.action === "import") return { mode: "import", errors };
    if (deleteError.length) {
      const user = users.find((u) => u.id === Number(old.id));
      return user ? { mode: "delete", user, errors: deleteError } : null;
    }
    return {
      mode: old.action === "edit" ? "edit" : "add",
      id: old.id,
      initial: old,
      errors,
    };
  });

  const needle = query.trim().toLowerCase();
  const visible = useMemo(
    () =>
      users.filter((user) => {
        if (role && user.role !== role) return false;
        if (!needle) return true;
        return `${user.firstname} ${user.lastname} ${user.username} ${user.email} ${user.idnumber}`
          .toLowerCase()
          .includes(needle);
      }),
    [users, role, needle],
  );

  const count = (name) => users.filter((u) => u.role === name).length;
  const openImport = () => setModal({ mode: "import", errors: [] });
  const openAdd = () =>
    setModal({ mode: "add", id: 0, initial: EMPTY_FORM, errors: [] });
  const openEdit = (user) =>
    setModal({
      mode: "edit",
      id: user.id,
      initial: {
        username: user.username,
        firstname: user.firstname,
        lastname: user.lastname,
        email: user.email,
        idnumber: user.idnumber,
        role: user.role,
      },
      errors: [],
    });
  const filtered = Boolean(needle || role);

  return (
    <div className="container-xl">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">Users</h1>
          <p className="text-body-secondary mb-0">
            Add, edit and delete accounts. Changes are applied in the LMS too.
          </p>
        </div>
        <div className="d-flex flex-wrap gap-2">
          <button
            type="button"
            className="btn btn-outline-primary"
            onClick={openImport}
            disabled={!moodleReady}
          >
            <i className="fas fa-file-import me-1" aria-hidden="true"></i>Import
            CSV
          </button>
          <button
            type="button"
            className="btn btn-primary"
            onClick={openAdd}
            disabled={!moodleReady}
          >
            <i className="fas fa-user-plus me-1" aria-hidden="true"></i>Add user
          </button>
        </div>
      </div>

      {!moodleReady && (
        <div
          className="alert alert-warning small d-flex align-items-start gap-2"
          role="alert"
        >
          <i
            className="fas fa-triangle-exclamation mt-1"
            aria-hidden="true"
          ></i>
          <div>
            {moodleMessage} Adding, editing and deleting are turned off until
            this is fixed, so the project and LMS never get out of step.
          </div>
        </div>
      )}

      {flash && (
        <div
          className={`alert alert-${flash[0]} d-flex align-items-center gap-2`}
          role="status"
        >
          <i className="fas fa-circle-check" aria-hidden="true"></i>
          {flash[1]}
        </div>
      )}

      <div className="row g-3 mb-4">
        <StatCard icon="fa-users" value={users.length} label="Users" />
        <StatCard
          icon="fa-user-graduate"
          value={count("student")}
          label="Students"
        />
        <StatCard
          icon="fa-chalkboard-user"
          value={count("teacher")}
          label="Teachers"
        />
        <StatCard icon="fa-user-shield" value={count("admin")} label="Admins" />
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="row g-2 align-items-center mb-3">
            <div className="col-md-6 col-lg-5">
              <div className="input-group">
                <span className="input-group-text">
                  <i className="fas fa-search" aria-hidden="true"></i>
                </span>
                <input
                  type="search"
                  className="form-control"
                  placeholder="Search by name, username or email"
                  aria-label="Search users"
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                />
              </div>
            </div>
            <div className="col-md-6 col-lg-4">
              <select
                className="form-select"
                aria-label="Filter by role"
                value={role}
                onChange={(event) => setRole(event.target.value)}
              >
                <option value="">All roles</option>
                {Object.entries(roles).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </div>
            <div
              className="col-lg-3 text-lg-end small text-body-secondary"
              aria-live="polite"
            >
              Showing {visible.length} of {users.length}
            </div>
          </div>

          {visible.length ? (
            <div className="table-responsive position-relative">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr className="small text-body-secondary">
                    <th scope="col">Name</th>
                    <th scope="col">Username</th>
                    <th scope="col">Role</th>
                    <th scope="col">LMS</th>
                    <th scope="col" className="text-end">
                      <span className="visually-hidden">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {visible.map((user) => (
                    <tr key={user.id}>
                      <td>
                        <div className="fw-semibold">
                          {user.firstname} {user.lastname}
                        </div>
                        <div className="small text-body-secondary">
                          {user.email}
                        </div>
                      </td>
                      <td className="text-nowrap">
                        {user.username}
                        {user.idnumber && (
                          <div className="small text-body-secondary">
                            {user.idnumber}
                          </div>
                        )}
                      </td>
                      <td>
                        <span
                          className={`badge bg-${ROLE_TONE[user.role] || "secondary"}-subtle text-${ROLE_TONE[user.role] || "secondary"}-emphasis`}
                        >
                          {roles[user.role] || user.role}
                        </span>
                      </td>
                      <td className="text-nowrap">
                        {user.moodleId ? (
                          <span className="badge bg-success-subtle text-success-emphasis">
                            <i
                              className="fas fa-link me-1"
                              aria-hidden="true"
                            ></i>
                            ID {user.moodleId}
                          </span>
                        ) : (
                          <span className="badge bg-secondary-subtle text-secondary-emphasis">
                            Not linked
                          </span>
                        )}
                      </td>
                      <td className="text-end text-nowrap">
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-primary me-1"
                          onClick={() => openEdit(user)}
                          disabled={!moodleReady || !user.moodleId}
                          aria-label={`Edit ${user.username}`}
                        >
                          <i className="fas fa-pen me-1" aria-hidden="true"></i>
                          Edit
                        </button>
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-danger"
                          onClick={() =>
                            setModal({ mode: "delete", user, errors: [] })
                          }
                          disabled={!moodleReady}
                          aria-label={`Delete ${user.username}`}
                        >
                          <i
                            className="fas fa-trash me-1"
                            aria-hidden="true"
                          ></i>
                          Delete
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center text-body-secondary py-5">
              {filtered ? (
                <>
                  <p className="mb-3">No users match your search.</p>
                  <button
                    type="button"
                    className="btn btn-outline-secondary btn-sm"
                    onClick={() => {
                      setQuery("");
                      setRole("");
                    }}
                  >
                    Clear search and filter
                  </button>
                </>
              ) : (
                <p className="mb-0">There are no users yet.</p>
              )}
            </div>
          )}
        </div>
      </div>

      {modal && modal.mode === "importresult" && (
        <ImportResult result={importResult} onClose={() => setModal(null)} />
      )}
      {modal && modal.mode === "import" && (
        <ImportForm
          errors={modal.errors}
          csrf={csrf}
          max={importMax}
          onClose={() => setModal(null)}
        />
      )}
      {modal && modal.mode === "delete" && (
        <DeleteUser
          user={modal.user}
          csrf={csrf}
          errors={modal.errors}
          onClose={() => setModal(null)}
        />
      )}
      {modal && ["add", "edit"].includes(modal.mode) && (
        <UserForm
          key={`${modal.mode}-${modal.id}`}
          mode={modal.mode}
          userId={modal.id}
          initial={{ ...EMPTY_FORM, ...modal.initial }}
          errors={modal.errors}
          roles={roles}
          csrf={csrf}
          onClose={() => setModal(null)}
        />
      )}
    </div>
  );
}
