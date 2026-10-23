import { useState } from "react";

function toMin(t) {
  const [h, m] = t.split(":");
  return Number(h) * 60 + Number(m);
}

function fmt(t) {
  const [h, m] = t.split(":").map(Number);
  const ampm = h >= 12 ? "PM" : "AM";
  const hh = h % 12 || 12;
  return `${hh}:${String(m).padStart(2, "0")} ${ampm}`;
}

function slotLabel(mins) {
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  const ampm = h >= 12 ? "PM" : "AM";
  const hh = h % 12 || 12;
  return `${hh}:${String(m).padStart(2, "0")} ${ampm}`;
}

const DAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const TONES = ["primary", "success", "warning", "info", "danger", "secondary"];
const GRID_START = 7 * 60;
const GRID_END = 18 * 60;
const STEP = 30;
const SLOTS = (GRID_END - GRID_START) / STEP;

export default function Schedule({ student, term, today, subjects }) {
  const [activeTab, setActiveTab] = useState("list");

  const totalUnits = subjects.reduce((sum, s) => sum + (s.units || 0), 0);
  let weeklyHours = 0;
  let todayCount = 0;
  for (const s of subjects) {
    weeklyHours += ((toMin(s.end) - toMin(s.start)) * s.days.length) / 60;
    if (s.days.includes(today)) todayCount++;
  }

  // Build weekly grid
  const cells = {};
  const skip = {};
  subjects.forEach((s, i) => {
    const first = Math.floor((toMin(s.start) - GRID_START) / STEP);
    const span = Math.ceil((toMin(s.end) - toMin(s.start)) / STEP);
    for (const d of s.days) {
      cells[d] = cells[d] || {};
      cells[d][first] = { subject: s, span, tone: TONES[i % TONES.length] };
      skip[d] = skip[d] || new Set();
      for (let k = first + 1; k < first + span; k++) skip[d].add(k);
    }
  });

  return (
    <div className="container-xl">
      {/* Page header */}
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">Class schedule</h1>
          <p className="text-body-secondary mb-2">{term}</p>

          <div className="small">
            <div>
              <span className="text-body-secondary">Student:</span>{" "}
              <span className="fw-semibold">
                {student.name} ({student.id})
              </span>
            </div>
            <div>
              <span className="text-body-secondary">Grade:</span>{" "}
              <span className="fw-semibold">{student.grade}</span>
            </div>
            <div>
              <span className="text-body-secondary">Section:</span>{" "}
              <span className="fw-semibold">{student.section}</span>
            </div>
          </div>
        </div>
        <div className="d-flex gap-2 d-print-none">
          <select
            className="form-select w-auto"
            aria-label="Select term"
            defaultValue={term}
          >
            <option>{term}</option>
            <option>2nd Semester, AY 2025-2026</option>
            <option>1st Semester, AY 2025-2026</option>
          </select>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => window.print()}
          >
            <i className="fas fa-file-pdf"></i>
          </button>
        </div>
      </div>

      {/* View switcher */}
      <ul className="nav nav-pills mb-3 d-print-none" role="tablist">
        <li className="nav-item" role="presentation">
          <button
            className={`nav-link ${activeTab === "list" ? "active" : ""}`}
            type="button"
            role="tab"
            onClick={() => setActiveTab("list")}
          >
            <i className="fas fa-list me-1"></i>List view
          </button>
        </li>
        <li className="nav-item" role="presentation">
          <button
            className={`nav-link ${activeTab === "week" ? "active" : ""}`}
            type="button"
            role="tab"
            onClick={() => setActiveTab("week")}
          >
            <i className="fas fa-table-cells me-1"></i>Weekly view
          </button>
        </li>
      </ul>

      <div className="tab-content">
        {/* List view */}
        {activeTab === "list" && (
          <div className="tab-pane fade show active" role="tabpanel">
            <div className="card border-0 shadow-sm">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th scope="col">Subject</th>
                      <th scope="col" className="text-center">
                        Units
                      </th>
                      <th scope="col">Days</th>
                      <th scope="col">Time</th>
                      <th scope="col">Room</th>
                      <th scope="col">Instructor</th>
                    </tr>
                  </thead>
                  <tbody>
                    {subjects.map((s, i) => (
                      <tr key={i}>
                        <td>
                          <div className="fw-semibold">
                            {s.code}
                            {s.days.includes(today) && (
                              <span className="badge bg-primary-subtle text-primary-emphasis ms-1">
                                Today
                              </span>
                            )}
                          </div>
                          <div className="small text-body-secondary">
                            {s.title}
                          </div>
                        </td>
                        <td className="text-center">{s.units}</td>
                        <td className="text-nowrap">{s.days.join(" / ")}</td>
                        <td className="text-nowrap">
                          {fmt(s.start)} - {fmt(s.end)}
                        </td>
                        <td className="text-nowrap">{s.room}</td>
                        <td className="text-nowrap">{s.instructor}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {/* Weekly grid */}
        {activeTab === "week" && (
          <div className="tab-pane fade show active" role="tabpanel">
            <div className="card border-0 shadow-sm">
              <div className="table-responsive">
                <table
                  className="table table-bordered table-sm align-middle mb-0"
                  style={{ minWidth: "760px" }}
                >
                  <thead className="table-light">
                    <tr>
                      <th
                        scope="col"
                        className="text-center"
                        style={{ width: "90px" }}
                      >
                        Time
                      </th>
                      {DAYS.map((d) => (
                        <th scope="col" className="text-center" key={d}>
                          {d}
                          {d === today && (
                            <span className="badge bg-primary-subtle text-primary-emphasis ms-1">
                              Today
                            </span>
                          )}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {Array.from({ length: SLOTS }, (_, i) => (
                      <tr key={i}>
                        <th
                          scope="row"
                          className="text-center small fw-normal text-body-secondary text-nowrap"
                        >
                          {slotLabel(GRID_START + i * STEP)}
                        </th>
                        {DAYS.map((d) => {
                          if (skip[d]?.has(i)) return null;
                          const c = cells[d]?.[i];
                          if (c) {
                            const s = c.subject;
                            return (
                              <td
                                key={d}
                                rowSpan={c.span}
                                className={`bg-${c.tone}-subtle text-${c.tone}-emphasis border-${c.tone}-subtle p-2 small`}
                              >
                                <div className="fw-semibold">{s.code}</div>
                                <div>
                                  {fmt(s.start)} - {fmt(s.end)}
                                </div>
                                <div>
                                  <i className="fas fa-location-dot me-1"></i>
                                  {s.room}
                                </div>
                              </td>
                            );
                          }
                          return <td key={d}></td>;
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
