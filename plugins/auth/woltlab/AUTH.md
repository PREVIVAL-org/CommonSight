# Auth provider: woltlab

| Section | Content |
|---|---|
| Admits | the logged-in members of a WoltLab Suite 6 forum: a session with a user, active within the last 60 days, whose user is not banned; logging out takes effect at once |
| Reads | the forum's session cookie (`<signature>-<base64>`: version 1, 20 bytes session ID, one byte time step; the signature is not checked) and one row of `wcfN_user_session` joined with `wcfN_user`, by primary key; without a cookie nothing is asked |
| Settings | `cookie` (name of the session cookie), `community` (name on the members card), `forumUrl` (address of the forum, ending with `/`), and either `database` (`host`, `port`, `name`, `user`, `password`, optionally `instance` for `wcfN_`) or `forumConfig` (path of the forum's `config.inc.php`) |
| Links | login `<forumUrl>index.php?login/&url=<start page>`, registration `<forumUrl>index.php?register/` |
| Needs | PHP `pdo_mysql`; forum and CommonSight on the same domain (or its subdomains with the forum's cookie domain), so the browser sends the cookie |
| Failures | database not reachable or configuration not readable: the page stays closed, `auth.failed` in the log |
| Code | `WoltlabAuthFactory` (settings), `WoltlabAuth` (decision, links), `SessionCookie`, `ForumDatabase` |
| History | from the former member gate (2026-10-02); removed on 2026-10-03, back as the first auth provider on 2026-10-04 |
