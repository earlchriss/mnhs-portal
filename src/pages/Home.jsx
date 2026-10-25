import { useState, useEffect, useCallback, useRef } from "react";
import { useMoodleLive } from "../useMoodleLive";

const EVALUATIONS_URL = "evaluations.php";

/**
 * Loads the faculty and service evaluation forms from evaluations.php.
 * Starts as soon as Home mounts. index.php may already have started the request
 * (window.__evalRequest) while the bundle was downloading; if not, fetch here.
 *
 * If `initial` is passed (data embedded by index.php), the forms render on the
 * very first paint and the request only refreshes them in the background.
 */
function useEvaluations(initial) {
  const [state, setState] = useState({
    faculty: initial?.faculty ?? [],
    services: initial?.services ?? [],
    ready: Boolean(initial), // true once real data (or a final error) is known
    error: "",
    signedOut: false,
  });
  const controllerRef = useRef(null);

  const load = useCallback(async () => {
    controllerRef.current?.abort();
    const controller = new AbortController();
    controllerRef.current = controller;

    try {
      const preloaded = window.__evalRequest;
      window.__evalRequest = null; // only ever used once
      let res = preloaded ? await preloaded : null;
      if (!res) {
        res = await fetch(EVALUATIONS_URL, {
          credentials: "same-origin",
          cache: "no-store",
          headers: { Accept: "application/json" },
          signal: controller.signal,
        });
      }

      let data = null;
      try {
        data = await res.json();
      } catch {
        // the server did not send JSON
      }
      if (controller.signal.aborted) return;

      if (res.status === 401) {
        setState((s) => ({
          ...s,
          ready: true,
          signedOut: true,
          error:
            data?.message || "Your session has ended. Please sign in again.",
        }));
        return;
      }
      if (!res.ok || !data?.ok) {
        setState((s) => ({
          ...s,
          ready: true,
          error:
            data?.message ||
            "Evaluations could not be loaded. Please try again.",
        }));
        return;
      }
      setState({
        faculty: data.faculty ?? [],
        services: data.services ?? [],
        ready: true,
        error: "",
        signedOut: false,
      });
    } catch (err) {
      if (err?.name === "AbortError") return;
      setState((s) => ({
        ...s,
        ready: true,
        error:
          "Could not reach the server. Check your connection and try again.",
      }));
    }
  }, []);

  useEffect(() => {
    load();
    // Coming back with the Back button restores the old page from memory; refresh
    // quietly so a form that was just submitted shows as completed.
    const onPageShow = (e) => {
      if (e.persisted) load();
    };
    window.addEventListener("pageshow", onPageShow);
    return () => {
      window.removeEventListener("pageshow", onPageShow);
      controllerRef.current?.abort();
    };
  }, [load]);

  return { ...state, reload: load };
}

function StatusBadge({ status }) {
  return status === "completed" ? (
    <span className="badge bg-success-subtle text-success-emphasis">
      Completed
    </span>
  ) : (
    <span className="badge bg-warning-subtle text-warning-emphasis">
      Pending
    </span>
  );
}

function FacultyEvaluation({ forms = [], ready = false, failed = false }) {
  const [selected, setSelected] = useState("");
  const pending = forms.filter((f) => f.status !== "completed");
  const completed = forms.filter((f) => f.status === "completed");

  useEffect(() => {
    const reset = () => setSelected("");
    window.addEventListener("pageshow", reset);
    return () => window.removeEventListener("pageshow", reset);
  }, []);

  function handleChange(e) {
    const value = e.target.value;
    setSelected(value);
    if (!value) return;
    window.location.href = `evaluate.php?type=faculty&id=${encodeURIComponent(value)}`;
  }

  // Don't claim "nothing available" before the first response has arrived.
  const placeholder =
    failed && forms.length === 0
      ? "Evaluations could not be loaded"
      : pending.length || !ready
        ? "Choose a teacher."
        : forms.length
          ? "All faculty evaluations completed"
          : "No faculty evaluations are available yet";

  return (
    <section className="mb-4" id="faculty">
      <div className="d-flex justify-content-between align-items-baseline mb-3">
        <h2 className="h5 fw-bold mb-0">Faculty evaluation</h2>
        <span className="small text-body-secondary">
          {completed.length} of {forms.length} completed
        </span>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-body p-3 p-md-4">
          <label htmlFor="facultySelect" className="form-label fw-semibold">
            Select a teacher to evaluate
          </label>
          <select
            className="form-select w-100 text-truncate"
            id="facultySelect"
            value={selected}
            onChange={handleChange}
            disabled={pending.length === 0}
          >
            <option value="" disabled>
              {placeholder}
            </option>
            {pending.map((f) => (
              <option key={f.id} value={f.id}>
                {f.instructor} ({f.code})
              </option>
            ))}
          </select>
          <div className="form-text">
            Choosing a teacher opens their evaluation form. Teachers you have
            already evaluated are not listed.
          </div>
        </div>
      </div>
    </section>
  );
}

function ServiceEvaluation({ forms = [], ready = false, failed = false }) {
  return (
    <section id="services">
      <div className="d-flex justify-content-between align-items-baseline mb-3">
        <h2 className="h5 fw-bold mb-0">Office and service evaluation</h2>
        <span className="small text-body-secondary">{forms.length} forms</span>
      </div>

      {ready && !failed && forms.length === 0 && (
        <div className="card border-0 shadow-sm">
          <div className="card-body text-center text-body-secondary small py-4">
            No office or service evaluations are available yet.
          </div>
        </div>
      )}

      <div className="row g-3">
        {forms.map((f) => {
          const isDone = f.status === "completed";
          return (
            <div className="col-sm-6" key={f.id}>
              <div className="card border-0 shadow-sm h-100">
                <div className="card-body d-flex align-items-center gap-3">
                  <span className="icon-box rounded-3 fs-5 bg-primary-subtle text-primary-emphasis">
                    <i className={`fas ${f.icon}`}></i>
                  </span>

                  <div className="flex-grow-1" style={{ minWidth: 0 }}>
                    <div className="fw-semibold lh-sm mb-1">{f.title}</div>
                    <StatusBadge status={f.status} />
                  </div>

                  {!isDone && (
                    <a
                      href={`evaluate.php?type=service&id=${encodeURIComponent(f.id)}`}
                      className="btn btn-sm btn-outline-primary stretched-link"
                      aria-label={`Evaluate ${f.title}`}
                    >
                      <i className="fas fa-arrow-right"></i>
                    </a>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}

export default function Home({ student, term, today, greeting, initial }) {
  const { faculty, services, ready, error, signedOut, reload } =
    useEvaluations(initial);
  const [notice, setNotice] = useState("");

  // Anything new on Moodle (a post, an announcement, a new evaluation form...):
  // reload the forms right away and tell the student.
  useMoodleLive((info, previous) => {
    reload();
    const isNewNotification =
      info.latest && info.latest.id !== previous?.latest?.id;
    setNotice(
      isNewNotification
        ? `New in LMS: ${info.latest.subject}`
        : "Something new was posted in LMS. This page has been updated.",
    );
  });

  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(""), 8000);
    return () => clearTimeout(timer);
  }, [notice]);

  return (
    <div className="container-xl">
      {/* Welcome banner */}
      <section className="welcome text-white rounded-4 p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <p className="small opacity-75 mb-1">{today}</p>
          <h1 className="h3 fw-bold mb-1">
            {greeting}, {student.first_name}!
          </h1>
          <p className="mb-0 opacity-75">
            {student.program}, {student.year} &middot; {student.section}{" "}
            &middot; ID {student.id}
          </p>
        </div>
        <span className="badge rounded-pill text-bg-light text-primary fs-6 fw-semibold px-3 py-2">
          {term}
        </span>
      </section>

      {notice && (
        <div
          className="alert alert-info small d-flex align-items-center gap-2"
          role="status"
        >
          <i className="fas fa-bell"></i>
          <span className="flex-grow-1">{notice}</span>
          <button
            type="button"
            className="btn-close"
            aria-label="Dismiss"
            onClick={() => setNotice("")}
          ></button>
        </div>
      )}

      {error && (
        <div
          className="alert alert-warning small d-flex align-items-center gap-2"
          role="alert"
        >
          <i className="fas fa-triangle-exclamation"></i>
          <span className="flex-grow-1">{error}</span>
          {signedOut ? (
            <a href="login/" className="btn btn-sm btn-outline-secondary">
              Sign in
            </a>
          ) : (
            <button
              type="button"
              className="btn btn-sm btn-outline-secondary"
              onClick={() => reload()}
            >
              Try again
            </button>
          )}
        </div>
      )}

      <div className="row g-4">
        <div className="col-lg-5">
          <FacultyEvaluation
            forms={faculty}
            ready={ready}
            failed={Boolean(error)}
          />
        </div>
        <aside className="col-lg-7">
          <ServiceEvaluation
            forms={services}
            ready={ready}
            failed={Boolean(error)}
          />
        </aside>
      </div>
    </div>
  );
}
