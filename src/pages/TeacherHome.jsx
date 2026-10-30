export default function TeacherHome({ name, greeting, today, schedule = [] }) {
  const subjects = new Set(schedule.map((row) => row.code)).size;
  const sections = new Set(schedule.map((row) => row.section)).size;
  return (
    <div className="container-xl py-4">
      <div className="mb-4">
        <div className="text-body-secondary small">{today}</div>
        <h1 className="h3 fw-bold mt-1">{greeting}, {name}</h1>
        <p className="text-body-secondary mb-0">Welcome to your teacher portal.</p>
      </div>
      <div className="row g-3 mb-4">
        {[["fa-book", subjects, "Assigned subjects"], ["fa-users", sections, "Assigned sections"], ["fa-calendar", schedule.length, "Schedule entries"]].map(([icon, value, label]) => (
          <div className="col-12 col-sm-4" key={label}><div className="card border-0 shadow-sm h-100"><div className="card-body d-flex align-items-center gap-3"><span className="icon-box rounded-3 fs-5 bg-primary-subtle text-primary-emphasis"><i className={`fas ${icon}`}></i></span><div><div className="fs-4 fw-bold lh-1">{value}</div><div className="small text-body-secondary">{label}</div></div></div></div></div>
        ))}
      </div>
      <section id="schedule"><h2 className="h5 fw-bold mb-3">My classes &amp; schedule</h2><div className="card border-0 shadow-sm">
        {schedule.length === 0 ? <div className="card-body text-body-secondary">No schedules have been assigned to your account yet.</div> :
          <div className="table-responsive"><table className="table table-hover align-middle mb-0"><thead className="table-light"><tr><th>Subject</th><th>Section</th><th>Days</th><th>Time</th><th>Room</th><th><span className="visually-hidden">Class</span></th></tr></thead><tbody>{schedule.map((row) => <tr key={row.id}><td><div className="fw-semibold">{row.title}</div><div className="small text-body-secondary">{row.code}</div></td><td>{row.section}</td><td>{row.days}</td><td>{row.start_time}–{row.end_time}</td><td>{row.room}</td><td><a className="btn btn-sm btn-outline-primary" href={`/teacher/class.php?id=${encodeURIComponent(row.id)}`}>Students &amp; grades</a></td></tr>)}</tbody></table></div>}
      </div></section>
    </div>
  );
}
