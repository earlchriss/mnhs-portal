<?php
$navUser = function_exists("auth_user") ? auth_user() : null;
$navName = $navUser["name"] ?? "Student";
$navParts = preg_split('/\s+/', trim($navName)) ?: [];
$navInitials = strtoupper(
    mb_substr($navParts[0] ?? "S", 0, 1) . (count($navParts) > 1 ? mb_substr(end($navParts), 0, 1) : "")
);
$navCsrf = function_exists("auth_csrf_token") ? auth_csrf_token() : "";
?>
<!-- Navbar -->
<nav class="moodle-navbar">
    <button type="button" class="nav-icon-btn" id="drawerOpen" aria-controls="drawer" aria-expanded="false" aria-label="Open navigation">
        <i class="fas fa-bars"></i>
    </button>

    <div class="navbar-right">
        <!-- Notifications -->
        <div class="dropdown" id="notifDropdown" data-csrf="<?= htmlspecialchars($navCsrf, ENT_QUOTES) ?>">
            <button type="button" class="nav-icon-btn position-relative" id="notifToggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="count-badge d-none" id="notifCount">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="width: min(380px, 94vw);" aria-labelledby="notifToggle">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <span class="fw-semibold">Notifications</span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="notifMarkAll" disabled>Mark all as read</button>
                </div>
                <div id="notifList" style="max-height: 420px; overflow-y: auto;" aria-live="polite">
                    <div class="text-center text-body-secondary small py-4">Loading…</div>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button class="user-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar"><?= htmlspecialchars($navInitials) ?></span>
                <i class="fas fa-chevron-down caret"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">Profile</a></li>
                <li><a class="dropdown-item" href="#">Settings</a></li>
                <li><a class="dropdown-item" href="#">Preferences</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Drawer -->
<aside class="moodle-drawer" id="drawer" aria-hidden="true">
    <div class="drawer-header">
        <button type="button" class="drawer-close" id="drawerClose" aria-label="Close navigation">
            <i class="fas fa-xmark"></i>
        </button>
        <img src="/assets/img/banner.png" alt="Mabolo National High School" class="drawer-logo">
    </div>
    <nav class="drawer-nav">
        <a class="drawer-link <?= isActive('index.php') ?>" href="/">Home</a>
        <a class="drawer-link <?= isActive('assignments.php') ?>" href="/assignments.php">Assignments</a>
        <a class="drawer-link <?= isActive('schedule.php') ?>" href="/schedule.php">Schedule</a>
        <a class="drawer-link <?= isActive('egrade.php') ?>" href="/egrade.php">E-Grade</a>
    </nav>
</aside>
<div class="drawer-backdrop" id="drawerBackdrop"></div>

<script>
    (function() {
        var ENDPOINT = "/notifications.php";
        var POLL_MS = 60000;
        var dropdown = document.getElementById("notifDropdown");
        var badge = document.getElementById("notifCount");
        var list = document.getElementById("notifList");
        var markAll = document.getElementById("notifMarkAll");
        if (!dropdown || !badge || !list || !markAll) return;

        function setBadge(count) {
            badge.textContent = count > 99 ? "99+" : String(count);
            badge.classList.toggle("d-none", count < 1);
            markAll.disabled = count < 1;
        }

        function message(text) {
            list.innerHTML = "";
            var box = document.createElement("div");
            box.className = "text-center text-body-secondary small py-4 px-3";
            box.textContent = text;
            list.appendChild(box);
        }

        function render(items) {
            if (!items.length) {
                message("You have no notifications.");
                return;
            }
            list.innerHTML = "";
            items.forEach(function(n) {
                var row = document.createElement(n.url ? "a" : "div");
                row.className = "d-block px-3 py-2 border-bottom text-reset text-decoration-none" + (n.read ? "" : " bg-primary-subtle");
                if (n.url) {
                    row.href = n.url;
                    row.target = "_blank";
                    row.rel = "noreferrer";
                }
                row.dataset.id = n.id;

                var title = document.createElement("div");
                title.className = "small " + (n.read ? "" : "fw-semibold");
                title.textContent = n.subject;
                row.appendChild(title);

                if (n.text && n.text !== n.subject) {
                    var body = document.createElement("div");
                    body.className = "small text-body-secondary";
                    body.textContent = n.text.length > 140 ? n.text.slice(0, 140) + "…" : n.text;
                    row.appendChild(body);
                }
                if (n.time) {
                    var time = document.createElement("div");
                    time.className = "text-body-secondary";
                    time.style.fontSize = "0.75rem";
                    time.textContent = n.time;
                    row.appendChild(time);
                }

                row.addEventListener("click", function() {
                    if (!n.read) post("read", {
                        id: n.id
                    });
                });
                list.appendChild(row);
            });
        }

        function load(showLoading) {
            if (showLoading) message("Loading…");
            return fetch(ENDPOINT, {
                    headers: {
                        Accept: "application/json"
                    },
                    credentials: "same-origin"
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (!data.ok) throw new Error(data.message || "Notifications are unavailable.");
                    setBadge(data.unread || 0);
                    render(data.notifications || []);
                })
                .catch(function(err) {
                    message(err.message || "Notifications could not be loaded.");
                });
        }

        function post(action, fields) {
            var body = new URLSearchParams(fields || {});
            return fetch(ENDPOINT + "?action=" + action, {
                method: "POST",
                headers: {
                    "X-CSRF-Token": dropdown.dataset.csrf || ""
                },
                body: body,
                credentials: "same-origin",
                keepalive: true
            }).then(function() {
                return load(false);
            }).catch(function() {});
        }

        markAll.addEventListener("click", function() {
            markAll.disabled = true;
            post("read_all");
        });

        dropdown.addEventListener("show.bs.dropdown", function() {
            load(true);
        });

        // Keep the unread badge fresh without opening the menu.
        function refreshBadge() {
            fetch(ENDPOINT, {
                    headers: {
                        Accept: "application/json"
                    },
                    credentials: "same-origin"
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.ok) setBadge(data.unread || 0);
                })
                .catch(function() {});
        }
        refreshBadge();
        setInterval(function() {
            if (!document.hidden) refreshBadge();
        }, POLL_MS);
    })();
</script>