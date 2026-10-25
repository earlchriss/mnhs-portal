import { createRoot } from "react-dom/client";
import Home from "./pages/Home.jsx";
import Schedule from "./pages/Schedule.jsx";
import EGrade from "./pages/EGrade.jsx";
import AdminHome from "./pages/AdminHome.jsx";
import AdminSchedule from "./pages/AdminSchedule.jsx";
import Assignments from "./pages/Assignments.jsx";
import Login from "./pages/Login.jsx";
import AdminSubjects from "./pages/AdminSubjects.jsx";
import AdminUsers from "./pages/AdminUsers.jsx";
import AdminFaculty from "./pages/AdminFaculty.jsx";

// Map the page name sent by PHP (data-page) to a React component.
// Add new pages here (Schedule, EGrade, ...).
const pages = {
  home: Home,
  schedule: Schedule,
  egrade: EGrade,
  "admin-home": AdminHome,
  "admin-schedule": AdminSchedule,
  assignments: Assignments,
  login: Login,
  "admin-subjects": AdminSubjects,
  "admin-users": AdminUsers,
  "admin-faculty": AdminFaculty,
};

const mount = document.getElementById("root");

if (mount) {
  const Page = pages[mount.dataset.page];
  // PHP puts its data in <script type="application/json" id="page-props">
  const propsEl = document.getElementById("page-props");
  const props = propsEl ? JSON.parse(propsEl.textContent) : {};

  if (Page) {
    createRoot(mount).render(<Page {...props} />);
  } else {
    console.error("Unknown page:", mount.dataset.page);
  }
}
