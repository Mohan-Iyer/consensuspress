# ShiftLog — Your AI Conversation Memory

**Every AI forgets. ShiftLog remembers.**

---

## The Problem Every Power AI User Knows

You've had thousands of conversations with Claude, ChatGPT, Gemini, and Mistral. You've solved problems, built systems, refined ideas, and made decisions — all inside chat windows that evaporate the moment a session ends.

You remember *that* conversation where the AI helped you crack a hard problem. But which session was it? Which platform? Which month?

AI tools are brilliant. Their memory is not.

**ShiftLog fixes this.** Upload your conversation exports. Search everything instantly. Find any idea, any decision, any code block — across every platform, every session, every year.

---

## What ShiftLog Does

ShiftLog is a SaaS platform that ingests your AI conversation exports and turns them into a permanent, searchable, structured knowledge base — yours alone.

**Upload once. Search forever.**

- Upload your ChatGPT or Claude export (JSON or ZIP, up to 250MB)
- ShiftLog parses every message, extracts every keyword, detects every code block
- Search across 2,000+ conversations in milliseconds
- Your data is yours — isolated, private, never shared

---

## Core Features

### Multi-Platform Ingestion
Upload exports from the AI platforms you actually use:

- **Claude** — full JSON export support ✅
- **ChatGPT** — full JSON export support ✅
- **Mistral le Chat** — coming soon
- **Google Gemini** — coming soon
- **Cohere** — coming soon

ShiftLog detects the platform automatically. No configuration required.

### Full-Text Search
Find anything across your entire conversation history. Search by keyword, phrase, topic, or concept. Results ranked by relevance. Filter by platform or date range.

### Automatic Keyword Extraction
Every conversation is automatically tagged with extracted keywords using TF-IDF analysis and named entity recognition. No manual tagging. No categories to maintain.

### Code Block Detection
ShiftLog identifies and indexes every code block in every conversation — with language detection (Python, JavaScript, SQL, Bash, and more). Find that snippet you wrote six months ago in seconds.

### Conversation Timeline
See your conversations organised chronologically. Browse by topic cluster. Understand how your thinking evolved across sessions and platforms.

### Topic Clustering *(Sprint 9)*
ShiftLog groups related conversations automatically using unsupervised machine learning. See which topics dominated your AI usage, which problems you returned to, and how your work evolved over time.

### Project History Recovery *(Architect Tier — coming)*
If you've used structured session notes or re-anchor statements in your AI workflows, ShiftLog detects them automatically and builds a chronological project timeline — decisions made, blockers cleared, milestones reached. This is the Re-Anchor Manager, productized.

---

## Privacy and Data Isolation

**Your conversations are yours. Full stop.**

ShiftLog is built on a strict per-user isolation model. Every database query is scoped to your user ID. No user can see, search, or access another user's data — by architecture, not just policy.

This is not a checkbox. It is a testable, verified guarantee (see the Test Verification section below).

---

## Pricing

| Tier | Price | What You Get |
|------|-------|--------------|
| **Explorer** | $7/month | Full-text search, Claude + ChatGPT parsers, up to X conversations |
| **Pro** | $19/month | Expanded parsers (Mistral, Gemini, Cohere), higher usage limits, topic clustering, timeline |
| **Architect** | TBD | Re-Anchor generation, project history recovery, Python automation, methodology backing |

*Annual plans available. Agency white-label pricing on request.*

---

## Who ShiftLog Is For

**Power AI users** who have accumulated months or years of conversation history and can't find anything.

**Developers** who use Claude or ChatGPT as a coding partner and need to retrieve code, decisions, and approaches from past sessions.

**Consultants and researchers** who conduct extended multi-session AI-assisted work and need to trace reasoning across conversations.

**Teams** using AI for strategic work who need an auditable record of AI-assisted decisions.

**Anyone who has felt the frustration of knowing the answer is somewhere in their chat history — but can't find it.**

---

## Why ShiftLog Is Different

Most AI conversation tools offer basic history viewing within a single platform. ShiftLog is different in three ways:

**1. Cross-platform.** Your Claude conversations and your ChatGPT conversations exist in the same search index. One search finds everything.

**2. Structured.** ShiftLog doesn't just store your conversations — it extracts structure. Keywords, code blocks, topics, and timelines emerge automatically.

**3. Privacy-first by architecture.** Per-user data isolation is enforced at the database level, not the application level. It is verified by automated tests that run on every deployment.

---

## Origin

ShiftLog began as a personal tool.

Mohan Iyer — developer, AI power user, and author of the Re-Anchor Manager methodology — had accumulated 349MB of AI conversation data across 2,000+ sessions by November 2025. The tool he built to manage it, Chat Archive, ran locally on his MacBook and solved the problem for himself.

ShiftLog is Chat Archive made available to everyone who has the same problem.

It is built by someone who uses it every day — not by a team that thought AI conversation management sounded like a good idea.

---

## The Technology

ShiftLog is built on a production-grade stack:

- **Backend:** FastAPI + Uvicorn (Python)
- **Database:** PostgreSQL (Railway) — full-text search via tsvector
- **Authentication:** JWT with bcrypt password hashing
- **Parsing:** Platform-native JSON export parsers for Claude and ChatGPT
- **Extraction:** TF-IDF keyword extraction, regex-based code block detection with language identification
- **Clustering:** Scikit-learn unsupervised topic clustering
- **Hosting:** Railway (auto-deploy from GitHub)
- **Frontend:** Jinja2 templates + HTMX (fast, no JavaScript framework)

---

## Test Verification — Executable Proof

ShiftLog ships with 152 automated tests. These are not internal metrics. They are executable proof of the platform's behaviour — run on every deployment, before any code reaches production.

### What the Tests Prove

**Authentication and Access Control (12 tests)**
- Passwords are hashed with bcrypt — never stored in plain text
- JWT access tokens expire in 30 minutes
- JWT refresh tokens expire in 7 days
- Expired tokens are rejected
- Tampered tokens are rejected
- Garbage tokens are rejected

**Data Layer and Isolation (28 tests)**
- Users can only see their own conversations
- Search is scoped to the authenticated user — never bleeds across accounts
- Duplicate uploads are detected and skipped — no double-counting
- Cross-user duplicate detection works correctly
- Cascading deletes remove all associated data when a conversation is deleted
- Pagination works correctly across large result sets
- Platform filtering works correctly
- Date range filtering works correctly

**Ingestion Pipeline (32 tests)**
- Claude JSON exports parse correctly end-to-end
- ChatGPT JSON exports parse correctly end-to-end
- Platform detection identifies the export source automatically
- Code blocks are extracted with correct language identification
- Keywords are extracted and stored correctly
- UTF-8 content survives the full ingestion pipeline — including emoji (🎉), CJK characters (你好), and right-to-left text (مرحبا)
- Memory bomb protection: a 500KB single message parses without memory overflow
- Empty content is handled gracefully — no crashes, no data loss

**API Endpoints (28 tests)**
- Registration, login, and token refresh work correctly
- Upload endpoint accepts both JSON and ZIP files
- Upload endpoint rejects oversized files
- Upload endpoint rejects invalid file formats
- Search endpoint returns correct results
- Search endpoint returns no results for non-matching queries
- Conversation detail endpoint returns correct data
- All endpoints reject unauthenticated requests
- All endpoints reject requests from the wrong user
- SQL injection: four classic attack payloads (DROP TABLE, UNION SELECT, OR 1=1, DELETE) return 200 with zero results — no data exposed, no errors triggered

**Web Interface (20 tests)**
- Login page renders correctly for unauthenticated users
- Dashboard redirects unauthenticated users to login
- Upload form submits correctly and shows confirmation
- Search renders results correctly
- Conversation detail view renders correctly
- Cross-user isolation: a user cannot view another user's conversation via URL manipulation

**Timeline and Topic Clustering (32 tests)**
- Timeline groups conversations correctly by date
- Topic clustering groups related conversations
- Cluster labels are generated correctly
- Edge cases handled: empty conversation sets, single conversations, corrupted keyword data
- Large dataset performance: clustering completes within acceptable time bounds

### Security Test Summary

| Attack Vector | Test | Result |
|---------------|------|--------|
| SQL Injection (DROP TABLE) | T4-38a | ✅ Blocked — 0 results |
| SQL Injection (UNION SELECT) | T4-38b | ✅ Blocked — 0 results |
| SQL Injection (OR 1=1) | T4-38c | ✅ Blocked — 0 results |
| SQL Injection (DELETE) | T4-38d | ✅ Blocked — 0 results |
| Cross-user data access via API | T7-01 | ✅ Blocked — 403 |
| Cross-user data access via URL | T5-10 | ✅ Blocked — 404 |
| Unauthenticated API access | T4-15 | ✅ Blocked — 401 |
| Tampered JWT | T1-11 | ✅ Rejected |
| Expired JWT | T1-10 | ✅ Rejected |
| Memory bomb upload | T3-27 | ✅ Handled — no OOM |
| Rollback atomicity | T2-26 | ✅ Verified — no orphaned rows |
| UTF-8 / emoji content | T3-26 | ✅ Survives pipeline intact |

### Deployment Verification

Every deployment passes a 6-gate validation pipeline before any code reaches Railway:

1. **HAL-001 structural scan** — zero type erasure violations (AST-based, not grep)
2. **Hallucination sweep** — seven classes of LLM code failure checked
3. **Indentation logic scan** — CCS-003 compliance
4. **File count gate** — deploy folder stays compact
5. **Version verification** — Procfile, requirements, sprint markers confirmed
6. **Railway confirmation** — explicit human approval before push

No code ships without passing all six gates. This is not optional and is not bypassed.

---

## Built With Axiom-First Development (AFD)
---

## Roadmap

**Now (v0.6.1 — Live)**
- Claude + ChatGPT parsers
- Full-text search
- Keyword extraction
- Code block detection
- Topic clustering
- Timeline view
- Per-user isolation

**Sprint 7 — Parsers**
- Mistral le Chat parser *(pending sample export)*
- Google Gemini parser *(pending sample export)*
- Cohere parser *(pending sample export)*

**Sprint 8 — UX**
- Upload walkthrough (step-by-step platform-specific guides)
- Drag-and-drop upload zone
- Post-upload summary dashboard

**Sprint 10+ — Architect Features**
- Re-Anchor generation (project history recovery from conversation chains)
- Longitudinal project timeline
- Decision and blocker tracking
- Export to structured formats

**Phase 2 Infrastructure**
- JWT revocation (Redis)
- Rate limiting
- Stripe integration
- Semantic search (embeddings)

---

## FAQ

**What formats does ShiftLog accept?**
JSON or ZIP files exported from Claude or ChatGPT. Up to 250MB per upload. For larger archives, ShiftLog provides a split utility.

**Is my data private?**
Yes. Per-user data isolation is enforced at the database level. Your conversations are scoped to your user ID on every query. No other user can see your data.

**Does ShiftLog store my conversation content permanently?**
Yes — that is the point. Your conversations are stored in PostgreSQL on Railway and persist across sessions. You can delete any conversation or your entire account at any time.

**Can I export my data?**
Data export is on the roadmap. In the current version, your data is accessible via the search and browse interface.

**Does ShiftLog read my conversations for training or analytics?**
No. Your conversations are stored and indexed for your search use only. They are not used for any other purpose.

**What happens if I upload the same export twice?**
ShiftLog detects duplicate conversations by content hash and skips them. You will not end up with duplicate entries.

**What is the Re-Anchor Manager?**
The Re-Anchor Manager is a session continuity methodology developed by Mohan Iyer for managing long-running AI development projects. If you have used structured session notes in your AI conversations, ShiftLog's Architect tier will automatically detect and reconstruct your project history from them.

---

## Get Started

ShiftLog is live at **app.shiftlog-ai.com**

1. Register with your email
2. Export your conversations from Claude or ChatGPT
3. Upload the export file
4. Search everything

Your AI memory starts now.

---

*ShiftLog is built and maintained by Mohan Iyer. Hosted on Railway. 

*Questions: mohan@pixels.net.nz*