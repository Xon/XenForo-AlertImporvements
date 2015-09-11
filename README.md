#XenForo-AlertImprovements

A collection of improvements to the XenForo Alerts system.

#Features
- For threads, automatically marks alerts as read from content on a given page when viewed.
- Mark as unread link for individual alerts

#Performance impact

- 1 extra query per thread page request when the user has more than zero active alerts.
- 1 extra query if any alerts are marked as read.
