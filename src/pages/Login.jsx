import { useState } from "react";

export default function Login({
  schoolName,
  logo,
  csrfToken,
  next = "/",
  endpoint = "/login/authenticate.php",
  forgotUrl,
}) {
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState("");

  // Use the prop if main.jsx forwarded it; otherwise read the page data directly.
  function getCsrfToken() {
    if (csrfToken) return csrfToken;
    try {
      const raw = document.getElementById("page-props")?.textContent || "{}";
      return JSON.parse(raw).csrfToken || "";
    } catch {
      return "";
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (isSubmitting) return;
    setMessage("");
    const token = getCsrfToken();
    if (!token) {
      setMessage("The sign-in form is out of date. Please reload the page.");
      return;
    }
    setIsSubmitting(true);

    const form = new FormData(event.currentTarget);
    try {
      const response = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
        body: JSON.stringify({
          username: form.get("username"),
          password: form.get("password"),
          remember: form.get("remember") === "on",
          next,
        }),
      });
      const data = await response.json().catch(() => ({}));
      if (response.ok && data.ok) {
        window.location.assign(data.redirect || "/");
        return; // keep the button disabled while the page navigates
      }
      setMessage(data.message || "Sign in failed. Please try again.");
    } catch {
      setMessage(
        "Could not reach the server. Check your connection and try again.",
      );
    }
    setIsSubmitting(false);
  }

  return (
    <div className="container min-vh-100 d-flex align-items-center py-4">
      <div className="row justify-content-center w-100 mx-0">
      <section className="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4" aria-label="Portal sign in">
      <div className="card shadow-sm">
      <div className="card-body p-4 p-sm-5">
        <div className="text-center mb-4">
          <img src={logo} alt={schoolName} className="img-fluid mb-4" />
          <h1 className="h3 fw-bold">Welcome back</h1>
          <p className="text-body-secondary mb-0">Sign in with your Moodle account to continue.</p>
        </div>
        {message && (
          <div className="alert alert-danger small" role="alert">
            {message}
          </div>
        )}
        <form onSubmit={handleSubmit}>
          <div className="mb-3">
            <label htmlFor="username" className="form-label fw-semibold">
              Username or email
            </label>
            <div className="input-group">
              <span className="input-group-text">
                <i className="fas fa-user" aria-hidden="true"></i>
              </span>
              <input
                type="text"
                className="form-control"
                id="username"
                name="username"
                autoComplete="username"
                placeholder="Enter your username or email"
                maxLength={100}
                autoFocus
                required
              />
            </div>
          </div>
          <div className="mb-3">
            <div className="d-flex justify-content-between align-items-center">
              <label htmlFor="password" className="form-label fw-semibold">
                Password
              </label>
              {forgotUrl && (
                <a
                  href={forgotUrl}
                  className="small text-decoration-none"
                  target="_blank"
                  rel="noreferrer"
                >
                  Forgot password?
                </a>
              )}
            </div>
            <div className="input-group">
              <span className="input-group-text">
                <i className="fas fa-lock" aria-hidden="true"></i>
              </span>
              <input
                type={showPassword ? "text" : "password"}
                className="form-control"
                id="password"
                name="password"
                autoComplete="current-password"
                placeholder="Enter your password"
                maxLength={200}
                required
              />
              <button
                type="button"
                className="btn btn-outline-secondary"
                onClick={() => setShowPassword((visible) => !visible)}
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                <i
                  className={`fas ${showPassword ? "fa-eye-slash" : "fa-eye"}`}
                  aria-hidden="true"
                ></i>
              </button>
            </div>
          </div>
          <div className="form-check mb-4">
            <input
              className="form-check-input"
              type="checkbox"
              id="remember"
              name="remember"
            />
            <label className="form-check-label small" htmlFor="remember">
              Remember me on this device
            </label>
          </div>
          <button
            type="submit"
            className="btn btn-primary w-100 py-2"
            disabled={isSubmitting}
          >
            {isSubmitting ? (
              <>
                <span
                  className="spinner-border spinner-border-sm me-2"
                  aria-hidden="true"
                ></span>
                Signing in...
              </>
            ) : (
              <>
                Sign in <i className="fas fa-arrow-right ms-2" aria-hidden="true"></i>
              </>
            )}
          </button>
        </form>
        <p className="text-body-secondary text-center small mt-4 mb-0">Your account is authenticated through Moodle.</p>
      </div>
      </div>
      </section>
      </div>
    </div>
  );
}
