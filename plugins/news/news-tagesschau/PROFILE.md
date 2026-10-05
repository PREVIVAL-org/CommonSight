# Profile: news-tagesschau

| Section | Content |
|---|---|
| Provider | tagesschau.de |
| Endpoint | `GET https://www.tagesschau.de/infoservices/alle-meldungen-100~rss2.xml` |
| Format | RSS 2.0 (the reader also accepts RDF and Atom); German |
| Authentication | none |
| License | rights with the provider; only headline, link and time are shown |
| Attribution | "Nachrichten: tagesschau.de" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every minute |
| Volume | some dozen entries |
| Quirks | only entries with a topic (weather, conflict, infrastructure) and a valid link are kept (`NewsTopicClassifier`) |
| Code | only `NewsTagesschauFactory`; feed reader, topic classifier and mapper are the news parts of the SDK (`CommonSight\Sdk\News`) |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
