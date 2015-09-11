# XenForo-AlertImprovements

A collection of improvements to the XenForo Alerts system.

Features:
- auto-mark alerts as read when visiting a thread

#Performance impact

- 1 extra query per thread page request when the user has more than zero active alerts.