import { useState, useEffect, useRef, useCallback } from "react";

const STATUS = {
  open: ["Open", "primary"],
  not_started: ["Not started", "secondary"],
  in_progress: ["In progress", "warning"],
  submitted: ["Submitted", "success"],
  late: ["Needs attention", "danger"],
};

function formatDue(value) {
  if (!value) return "Not specified";
  const date = new Date(value.replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return "Not specified";
  return date.toLocaleString([], {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function AssignmentCard({ assignment }) {
  const [label, tone] = STATUS[assignment.status] || STATUS.not_started;
  const isSubmitted = assignment.status === "submitted";

  return (
    <article className="card border-0 shadow-sm h-100">
      <div className="card-body d-flex flex-column">
        <div className="d-flex align-items-start justify-content-between gap-3 mb-3">
          <div>
            <div className="small fw-semibold text-primary">
              {assignment.course}
            </div>
            <h2 className="h5 fw-bold mb-1">{assignment.title}</h2>
            <div className="small text-body-secondary">
              {assignment.courseTitle}
            </div>
          </div>
          <span
            className={`badge bg-${tone}-subtle text-${tone}-emphasis text-nowrap`}
          >
            {label}
          </span>
        </div>
        <p className="small text-body-secondary mb-4">
          {assignment.description}
        </p>
        <div className="mt-auto border-top pt-3">
          <div className="row g-2 small mb-3">
            <div className="col-7">
              <span className="text-body-secondary d-block">Due</span>
              <span
                className={
                  assignment.status === "late"
                    ? "text-danger fw-semibold"
                    : "fw-semibold"
                }
              >
                {formatDue(assignment.due)}
              </span>
            </div>
            <div className="col-5">
              <span className="text-body-secondary d-block">Points</span>
              <span className="fw-semibold">{assignment.points ?? "—"}</span>
            </div>
          </div>
          {isSubmitted ? (
            <div className="small text-success-emphasis">
              <i className="fas fa-circle-check me-1"></i>Submitted{" "}
              {formatDue(assignment.submittedAt)}
            </div>
          ) : assignment.url ? (
            <a
              className="btn btn-outline-primary btn-sm"
              href={assignment.url}
              target="_blank"
              rel="noreferrer"
            >
              <i className="fas fa-arrow-up-right-from-square me-1"></i>
              Open assignment
            </a>
          ) : (
            <button
              type="button"
              className="btn btn-outline-secondary btn-sm"
              disabled
              title="This sample assignment is not linked to LMS."
            >
              <i className="fas fa-link-slash me-1"></i>
              Assignment link unavailable
            </button>
          )}
        </div>
      </div>
    </article>
  );
}

const FEED_URL = "assignments_feed.php";
const POLL_MS = 10000;

async function getJson(url) {
  const res = await fetch(url, {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  let body = null;
  try {
    body = await res.json();
  } catch {
    // not JSON (for example a login redirect)
  }
  if (!body) {
    throw Object.assign(new Error("The server sent an unexpected response."), {
      code: res.status,
    });
  }
  if (!res.ok || !body.ok) {
    throw Object.assign(
      new Error(body.message || "Assignments could not be loaded."),
      { code: res.status },
    );
  }
  return body;
}

function useAssignments() {
  const [assignments, setAssignments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null); // { message, expired }
  const [notice, setNotice] = useState(false);
  const version = useRef(null);
  const busy = useRef(false);
  const stopped = useRef(false);

  const load = useCallback(async ({ fresh = false, quiet = false } = {}) => {
    if (busy.current) return;
    busy.current = true;
    if (!quiet) setLoading(true);
    try {
      const body = await getJson(FEED_URL + (fresh ? "?fresh=1" : ""));
      version.current = body.version ?? version.current;
      setAssignments(body.data || []);
      setError(null);
    } catch (e) {
      if (e.code === 401) stopped.current = true;
      if (!quiet || e.code === 401) {
        setError({ message: e.message, expired: e.code === 401 });
      }
    } finally {
      busy.current = false;
      setLoading(false);
    }
  }, []);

  // First load, right away.
  useEffect(() => {
    load();
  }, [load]);

  // Watch Moodle: when something new is posted, reload the list.
  useEffect(() => {
    let timer = null;
    let failures = 0;
    let noticeTimer = null;

    async function check() {
      if (stopped.current || document.hidden || busy.current) return;
      try {
        const body = await getJson(FEED_URL + "?pulse=1");
        failures = 0;
        if (
          version.current !== null &&
          body.version &&
          body.version !== version.current
        ) {
          await load({ fresh: true, quiet: true });
          setNotice(true);
          clearTimeout(noticeTimer);
          noticeTimer = setTimeout(() => setNotice(false), 8000);
        } else if (version.current === null && body.version) {
          version.current = body.version;
        }
      } catch (e) {
        if (e.code === 401) {
          stopped.current = true;
          setError({ message: e.message, expired: true });
          return;
        }
        failures += 1;
      }
    }

    function schedule() {
      clearTimeout(timer);
      if (stopped.current) return;
      timer = setTimeout(
        async () => {
          await check();
          schedule();
        },
        POLL_MS * Math.min(1 + failures, 6),
      );
    }

    function onVisible() {
      if (!document.hidden) {
        check();
        schedule();
      }
    }

    schedule();
    document.addEventListener("visibilitychange", onVisible);
    window.addEventListener("pageshow", onVisible);
    return () => {
      clearTimeout(timer);
      clearTimeout(noticeTimer);
      document.removeEventListener("visibilitychange", onVisible);
      window.removeEventListener("pageshow", onVisible);
    };
  }, [load]);

  return {
    assignments,
    loading,
    error,
    notice,
    reload: () => load({ fresh: true }),
  };
}

function SkeletonCards() {
  return (
    <div className="row g-3" aria-busy="true" aria-label="Loading assignments">
      {[0, 1, 2].map((n) => (
        <div className="col-md-6 col-xl-4" key={n}>
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body placeholder-glow">
              <span className="placeholder col-4 mb-2"></span>
              <span className="placeholder col-9 mb-3"></span>
              <span className="placeholder col-12 mb-1"></span>
              <span className="placeholder col-10 mb-4"></span>
              <span className="placeholder col-6"></span>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

export default function Assignments({ student, term }) {
  const { assignments, loading, error, notice, reload } = useAssignments();
  const [filter, setFilter] = useState("all");
  const [query, setQuery] = useState("");
  const visible = assignments.filter((assignment) => {
    const matchesFilter =
      filter === "all" ||
      (filter === "pending"
        ? assignment.status !== "submitted"
        : assignment.status === filter);
    const haystack =
      `${assignment.course} ${assignment.courseTitle} ${assignment.title}`.toLowerCase();
    return matchesFilter && haystack.includes(query.toLowerCase());
  });

  return (
    <div className="container-xl">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">Assignments</h1>
          <p className="text-body-secondary mb-2">{term}</p>
          <div className="small">
            <span className="text-body-secondary">Student:</span>{" "}
            <span className="fw-semibold">
              {student.name} ({student.id})
            </span>
          </div>
        </div>
        <button
          type="button"
          className="btn btn-primary d-print-none"
          onClick={() => window.print()}
        >
          <i className="fas fa-print me-1"></i>Print
        </button>
      </div>

      {error ? (
        <div
          className="alert alert-warning small d-flex align-items-center gap-2"
          role="alert"
        >
          <i className="fas fa-triangle-exclamation"></i>
          <div className="flex-grow-1">{error.message}</div>
          {error.expired ? (
            <a className="btn btn-sm btn-outline-dark" href="login/">
              Sign in
            </a>
          ) : (
            <button
              type="button"
              className="btn btn-sm btn-outline-dark"
              onClick={reload}
            >
              Try again
            </button>
          )}
        </div>
      ) : notice ? (
        <div
          className="alert alert-primary small d-flex align-items-center gap-2"
          role="status"
        >
          <i className="fas fa-bell"></i>
          <div>
            Something new was posted in LMS. This page has been updated.
          </div>
        </div>
      ) : (
        <div
          className="alert alert-info small d-flex align-items-start gap-2"
          role="note"
        >
          <i className="fas fa-circle-info mt-1"></i>
          <div>
            Showing only subjects with LMS activities, quizzes, or other graded
            work. New posts appear here automatically.
          </div>
        </div>
      )}

      {assignments.length > 0 && (
        <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
          <ul className="nav nav-pills" role="tablist">
            {[
              ["all", "All"],
              ["pending", "To complete"],
              ["submitted", "Submitted"],
            ].map(([value, label]) => (
              <li className="nav-item" key={value}>
                <button
                  className={`nav-link ${filter === value ? "active" : ""}`}
                  type="button"
                  onClick={() => setFilter(value)}
                >
                  {label}
                </button>
              </li>
            ))}
          </ul>
          <div className="input-group" style={{ maxWidth: "320px" }}>
            <span className="input-group-text">
              <i className="fas fa-search"></i>
            </span>
            <input
              type="search"
              className="form-control"
              placeholder="Search assignments"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              aria-label="Search assignments"
            />
          </div>
        </div>
      )}

      {loading && assignments.length === 0 ? (
        <SkeletonCards />
      ) : visible.length ? (
        <div className="row g-3">
          {visible.map((assignment) => (
            <div className="col-md-6 col-xl-4" key={assignment.id}>
              <AssignmentCard assignment={assignment} />
            </div>
          ))}
        </div>
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="card-body text-center text-body-secondary py-5">
            {assignments.length
              ? "No assignments match your search."
              : error
                ? "Assignments are unavailable because LMS could not be reached."
                : "No activities, quizzes, or other graded work are available for your subjects."}
          </div>
        </div>
      )}
    </div>
  );
}
