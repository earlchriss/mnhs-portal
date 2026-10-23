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

function responseStatus(percent) {
  if (percent >= 75) return ["On track", "success"];
  if (percent >= 40) return ["In progress", "warning"];
  return ["Low response", "danger"];
}

function FacultyProgress({ stats }) {
  return (
    <section className="mb-4" id="faculty">
      <div className="d-flex justify-content-between align-items-baseline mb-3">
        <h2 className="h5 fw-bold mb-0">Faculty evaluation progress</h2>
        <a href="#" className="small text-decoration-none">
          View all reports
        </a>
      </div>
      <div className="card border-0 shadow-sm">
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0">
            <thead className="table-light">
              <tr>
                <th>Teacher</th>
                <th style={{ minWidth: "160px" }}>Responses</th>
                <th className="text-center">Avg rating</th>
                <th>Status</th>
                <th className="text-end">Report</th>
              </tr>
            </thead>
            <tbody>
              {stats.map((faculty) => {
                const percent = faculty.enrolled
                  ? Math.round((faculty.responses / faculty.enrolled) * 100)
                  : 0;
                const [label, color] = responseStatus(percent);
                return (
                  <tr key={faculty.code}>
                    <td>
                      <div className="fw-semibold">{faculty.instructor}</div>
                      <div className="small text-body-secondary">
                        {faculty.code} &middot; {faculty.title}
                      </div>
                    </td>
                    <td>
                      <div className="d-flex justify-content-between small mb-1">
                        <span className="fw-semibold">
                          {faculty.responses}/{faculty.enrolled}
                        </span>
                        <span className="text-body-secondary">{percent}%</span>
                      </div>
                      <div
                        className="progress"
                        style={{ height: "6px" }}
                        role="progressbar"
                        aria-label={`Responses for ${faculty.instructor}`}
                        aria-valuenow={percent}
                        aria-valuemin="0"
                        aria-valuemax="100"
                      >
                        <div
                          className={`progress-bar bg-${color}`}
                          style={{ width: `${percent}%` }}
                        ></div>
                      </div>
                    </td>
                    <td className="text-center fw-semibold">
                      {faculty.avg === null
                        ? "N/A"
                        : Number(faculty.avg).toFixed(2)}
                    </td>
                    <td>
                      <span
                        className={`badge bg-${color}-subtle text-${color}-emphasis`}
                      >
                        {label}
                      </span>
                    </td>
                    <td className="text-end">
                      <a
                        href="#"
                        className="btn btn-sm btn-outline-primary"
                        aria-label={`View report for ${faculty.instructor}`}
                      >
                        <i className="fas fa-chart-column"></i>
                      </a>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <div className="card-footer small text-body-secondary">
          Average ratings use the 1 (Poor) to 5 (Outstanding) scale. Student
          names are never shown, to keep answers confidential.
        </div>
      </div>
    </section>
  );
}

function RecentSubmissions({ recent }) {
  return (
    <section id="recent">
      <div className="d-flex justify-content-between align-items-baseline mb-3">
        <h2 className="h5 fw-bold mb-0">Recent submissions</h2>
        <a href="#" className="small text-decoration-none">
          View all
        </a>
      </div>
      <div className="card border-0 shadow-sm">
        <div className="list-group list-group-flush rounded-3">
          {recent.map((item) => (
            <div
              className="list-group-item p-3 d-flex justify-content-between align-items-center gap-3"
              key={`${item.subject}-${item.time}`}
            >
              <div>
                <div className="fw-semibold">{item.subject}</div>
                <div className="small text-body-secondary">
                  Section {item.section}
                </div>
              </div>
              <time className="small text-body-secondary text-nowrap">
                {item.time}
              </time>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function QuickActions() {
  const actions = [
    ["fa-chalkboard-user", "Faculty"],
    ["fa-list-check", "Questions"],
    ["fa-chart-column", "Reports"],
    ["fa-user-graduate", "Students"],
    ["fa-calendar-days", "Period"],
    ["fa-file-export", "Export"],
  ];
  return (
    <section className="mb-4">
      <h2 className="h5 fw-bold mb-3">Quick actions</h2>
      <div className="row g-2">
        {actions.map(([icon, label]) => (
          <div className="col-4" key={label}>
            <a
              href="#"
              className="btn btn-outline-primary w-100 d-flex flex-column align-items-center gap-2 py-3 small"
            >
              <i className={`fas ${icon} fs-5`}></i>
              {label}
            </a>
          </div>
        ))}
      </div>
    </section>
  );
}

function EvaluationPeriod({
  term,
  periodOpen,
  periodPercent,
  evalOpen,
  evalDeadline,
}) {
  return (
    <section className="mb-4">
      <h2 className="h5 fw-bold mb-3">Evaluation period</h2>
      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="d-flex justify-content-between align-items-center mb-3">
            <span className="fw-semibold">{term}</span>
            <span
              className={`badge ${periodOpen ? "bg-success-subtle text-success-emphasis" : "bg-secondary-subtle text-secondary-emphasis"}`}
            >
              {periodOpen ? "Open" : "Closed"}
            </span>
          </div>
          <div
            className="progress mb-2"
            style={{ height: "8px" }}
            role="progressbar"
            aria-label="Evaluation period elapsed"
            aria-valuenow={periodPercent}
            aria-valuemin="0"
            aria-valuemax="100"
          >
            <div
              className="progress-bar"
              style={{ width: `${periodPercent}%` }}
            ></div>
          </div>
          <div className="d-flex justify-content-between small text-body-secondary">
            <span>Opened {evalOpen}</span>
            <span>Closes {evalDeadline}</span>
          </div>
        </div>
      </div>
    </section>
  );
}

function NeedsAttention({ stats }) {
  const low = stats.filter(
    (faculty) =>
      faculty.enrolled && (faculty.responses / faculty.enrolled) * 100 < 40,
  );
  return (
    <section>
      <h2 className="h5 fw-bold mb-3">Needs attention</h2>
      <div className="card border-0 shadow-sm">
        <div className="list-group list-group-flush rounded-3">
          {low.length ? (
            low.map((faculty) => (
              <a
                href="#"
                className="list-group-item list-group-item-action p-3"
                key={faculty.code}
              >
                <div className="d-flex justify-content-between align-items-center mb-1">
                  <span className="badge rounded-pill bg-danger-subtle text-danger-emphasis">
                    Low response
                  </span>
                  <span className="small text-body-secondary">
                    {faculty.responses}/{faculty.enrolled}
                  </span>
                </div>
                <div className="fw-semibold">{faculty.instructor}</div>
                <div className="small text-body-secondary">
                  {faculty.code} &middot; {faculty.title}
                </div>
              </a>
            ))
          ) : (
            <div className="list-group-item p-3 text-center text-body-secondary small">
              Nothing needs attention.
            </div>
          )}
        </div>
      </div>
    </section>
  );
}

export default function AdminHome({
  admin,
  term,
  today,
  greeting,
  totalStudents,
  submitted,
  completion,
  daysLeft,
  periodOpen,
  periodPercent,
  evalOpen,
  evalDeadline,
  facultyStats,
  recent,
}) {
  return (
    <div className="container-xl">
      <section className="welcome text-white rounded-4 p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <p className="small opacity-75 mb-1">{today}</p>
          <h1 className="h3 fw-bold mb-1">
            {greeting}, {admin.first_name}!
          </h1>
          <p className="mb-0 opacity-75">
            {admin.role} &middot; Evaluation overview
          </p>
        </div>
        <span className="badge rounded-pill text-bg-light text-primary fs-6 fw-semibold px-3 py-2">
          {term}
        </span>
      </section>
      <section className="row g-3 mb-4" aria-label="Summary">
        <SummaryCard
          icon="fa-user-graduate"
          tone="primary"
          value={totalStudents.toLocaleString()}
          label="Students"
        />
        <SummaryCard
          icon="fa-clipboard-check"
          tone="success"
          value={submitted.toLocaleString()}
          label="Forms submitted"
        />
        <SummaryCard
          icon="fa-chart-pie"
          tone="warning"
          value={`${completion}%`}
          label="Completion rate"
        />
        <SummaryCard
          icon="fa-hourglass-half"
          tone={daysLeft <= 7 ? "danger" : "info"}
          value={Math.max(0, daysLeft)}
          label="Days left"
        />
      </section>
      <div className="row g-4">
        <div className="col-lg-8">
          <FacultyProgress stats={facultyStats} />
          <RecentSubmissions recent={recent} />
        </div>
        <aside className="col-lg-4">
          <QuickActions />
          <EvaluationPeriod
            term={term}
            periodOpen={periodOpen}
            periodPercent={periodPercent}
            evalOpen={evalOpen}
            evalDeadline={evalDeadline}
          />
          <NeedsAttention stats={facultyStats} />
        </aside>
      </div>
    </div>
  );
}
