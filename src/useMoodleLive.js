import { useEffect, useRef } from "react";

const CHANGES_URL = "changes.php";
const POLL_MS = 10000; // how often an open, visible page asks Moodle "anything new?"
const MAX_WAIT_MS = 120000; // slow down to this when Moodle keeps failing

/**
 * Watches Moodle for anything new (posts, announcements, activities, evaluation
 * forms, notifications). The first answer is only remembered. After that,
 * onChange(info, previous) runs every time the answer changes.
 *
 * It checks every POLL_MS while the page is visible, checks again the moment the
 * tab becomes visible, and stops by itself if the session has ended.
 */
export function useMoodleLive(onChange) {
  const handler = useRef(onChange);
  useEffect(() => {
    handler.current = onChange;
  });

  useEffect(() => {
    let stopped = false;
    let busy = false;
    let timer = null;
    let failures = 0;
    let last = null;
    const controller = new AbortController();

    function schedule() {
      if (stopped) return;
      const wait = Math.min(POLL_MS * 2 ** failures, MAX_WAIT_MS);
      timer = setTimeout(check, wait);
    }

    async function check() {
      if (stopped || busy) return;
      if (document.hidden) {
        schedule(); // nothing to do while nobody is looking
        return;
      }
      busy = true;
      try {
        const res = await fetch(CHANGES_URL, {
          credentials: "same-origin",
          cache: "no-store",
          headers: { Accept: "application/json" },
          signal: controller.signal,
        });
        if (res.status === 401) {
          stopped = true; // signed out: Home shows the sign-in message
          return;
        }
        const data = await res.json();
        if (!res.ok || !data?.ok) throw new Error("bad response");
        failures = 0;
        if (last && data.version !== last.version) {
          handler.current?.(data, last);
        }
        last = data;
      } catch (err) {
        if (err?.name === "AbortError") return;
        failures += 1;
      } finally {
        busy = false;
      }
      schedule();
    }

    function onVisible() {
      if (document.hidden || stopped) return;
      clearTimeout(timer);
      check(); // catch up straight away when the student comes back to the tab
    }

    document.addEventListener("visibilitychange", onVisible);
    check();

    return () => {
      stopped = true;
      clearTimeout(timer);
      controller.abort();
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, []);
}