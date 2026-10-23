function formatGrade(grade) {
  return grade === null ? "N/A" : Number(grade).toFixed(2);
}

function gradeClass(grade) {
  if (grade === null) return "text-body-secondary";
  if (grade <= 1.75) return "text-success-emphasis fw-semibold";
  if (grade <= 3) return "text-body";
  return "text-danger fw-semibold";
}

function getRemark(finalGrade) {
  if (finalGrade === null)
    return ["Ongoing", "bg-warning-subtle text-warning-emphasis"];
  if (finalGrade <= 3)
    return ["Passed", "bg-success-subtle text-success-emphasis"];
  return ["Failed", "bg-danger-subtle text-danger-emphasis"];
}

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

export default function EGrade({ student, terms, termKey, term, subjects }) {
  const totalUnits = subjects.reduce((sum, subject) => sum + subject.units, 0);
  const posted = subjects.filter((subject) => subject.final !== null);
  const postedUnits = posted.reduce((sum, subject) => sum + subject.units, 0);
  const weighted = posted.reduce(
    (sum, subject) => sum + subject.final * subject.units,
    0,
  );
  const gwa = postedUnits ? weighted / postedUnits : null;
  const passed = posted.filter((subject) => subject.final <= 3).length;
  const allPosted = posted.length === subjects.length;
  let standing = "Pending";
  let standingClass = "bg-secondary-subtle text-secondary-emphasis";
  if (allPosted && gwa !== null) {
    if (gwa <= 1.75 && passed === posted.length) {
      standing = "Dean's Lister";
      standingClass = "bg-success-subtle text-success-emphasis";
    } else if (passed === posted.length) {
      standing = "Good Standing";
      standingClass = "bg-primary-subtle text-primary-emphasis";
    } else {
      standing = "Needs Attention";
      standingClass = "bg-danger-subtle text-danger-emphasis";
    }
  }

  return (
    <div className="container-xl">
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h1 className="h3 fw-bold mb-1">E-Grade</h1>
          <p className="text-body-secondary mb-2">{term}</p>
          <div className="small">
            <span className="text-body-secondary">Student:</span>{" "}
            <span className="fw-semibold">
              {student.name} ({student.id})
            </span>
            <span className="text-body-secondary ms-3">Program:</span>{" "}
            <span className="fw-semibold">{student.program}</span>
          </div>
        </div>
        <div className="d-flex gap-2 d-print-none">
          <select
            className="form-select w-auto"
            value={termKey}
            onChange={(event) => {
              window.location.href = `egrade.php?term=${encodeURIComponent(event.target.value)}`;
            }}
            aria-label="Select term"
          >
            {Object.entries(terms).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => window.print()}
            aria-label="Print grades"
          >
            <i className="fas fa-file-pdf"></i>
          </button>
        </div>
      </div>

      <section className="row g-3 mb-4" aria-label="Grade summary">
        <SummaryCard
          icon="fa-book"
          tone="primary"
          value={subjects.length}
          label="Subjects"
        />
        <SummaryCard
          icon="fa-graduation-cap"
          tone="success"
          value={totalUnits}
          label="Total units"
        />
        <SummaryCard
          icon="fa-chart-line"
          tone="warning"
          value={gwa === null ? "N/A" : gwa.toFixed(2)}
          label="Current GWA"
        />
        <SummaryCard
          icon="fa-award"
          tone="info"
          value={<span className={`badge ${standingClass}`}>{standing}</span>}
          label="Academic standing"
        />
      </section>

      <div className="card border-0 shadow-sm mb-4">
        <div className="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
          <h2 className="h6 fw-semibold mb-0">
            <i className="fas fa-graduation-cap text-primary me-2"></i>Grades
          </h2>
          <span className="small text-body-secondary">
            {subjects.length} subjects
          </span>
        </div>
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0">
            <thead className="table-light">
              <tr>
                <th>Subject</th>
                <th className="text-center">Units</th>
                <th className="text-center">Prelim</th>
                <th className="text-center">Midterm</th>
                <th className="text-center">Pre-final</th>
                <th className="text-center">Final</th>
                <th className="text-center">Remarks</th>
              </tr>
            </thead>
            <tbody>
              {subjects.map((subject) => {
                const [remarkText, remarkClass] = getRemark(subject.final);
                return (
                  <tr key={subject.code}>
                    <td>
                      <div className="fw-semibold">{subject.code}</div>
                      <div className="small text-body-secondary">
                        {subject.title}
                      </div>
                      <div className="small text-body-secondary">
                        {subject.instructor}
                      </div>
                    </td>
                    <td className="text-center">{subject.units}</td>
                    {["prelim", "midterm", "prefinal", "final"].map(
                      (period) => (
                        <td
                          key={period}
                          className={`text-center ${gradeClass(subject[period])}`}
                        >
                          {formatGrade(subject[period])}
                        </td>
                      ),
                    )}
                    <td className="text-center">
                      <span className={`badge ${remarkClass}`}>
                        {remarkText}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      <div className="row g-3">
        <div className="col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-body-tertiary">
              <h2 className="h6 fw-semibold mb-0">
                <i className="fas fa-circle-info text-primary me-2"></i>Grading
                Scale
              </h2>
            </div>
            <div className="table-responsive">
              <table className="table table-sm table-hover mb-0">
                <thead className="table-light">
                  <tr>
                    <th>Grade</th>
                    <th>Equivalent</th>
                    <th>Description</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>1.00</td>
                    <td>97 - 100</td>
                    <td>Excellent</td>
                  </tr>
                  <tr>
                    <td>1.25 - 1.75</td>
                    <td>91 - 96</td>
                    <td>Very Good</td>
                  </tr>
                  <tr>
                    <td>2.00 - 2.50</td>
                    <td>85 - 90</td>
                    <td>Good</td>
                  </tr>
                  <tr>
                    <td>2.75 - 3.00</td>
                    <td>75 - 84</td>
                    <td>Passed</td>
                  </tr>
                  <tr>
                    <td>5.00</td>
                    <td>Below 75</td>
                    <td>Failed</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div className="col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <h2 className="h6 fw-semibold">
                <i className="fas fa-triangle-exclamation text-warning me-2"></i>
                Reminders
              </h2>
              <ul className="small text-body-secondary mb-0 ps-3">
                <li className="mb-2">
                  Grades shown here are unofficial until the registrar releases
                  the final grade sheet.
                </li>
                <li className="mb-2">
                  If you think a grade is wrong, talk to your instructor first,
                  then file a grade verification request.
                </li>
                <li>
                  The Academic Standing badge appears once every final grade for
                  the term is posted.
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
