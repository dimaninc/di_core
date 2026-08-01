# Pirlo smoke test

This file is a smoke test for the Pirlo agent deployment on the server.

It exists only to prove the end-to-end agent flow works: worktree checkout on the
`agent/DI-SMOKE-001` branch, file change, verification, and commit. No application
code is touched, and nothing here is used by `di_core` at runtime.

Safe to delete once the deployment has been verified.
