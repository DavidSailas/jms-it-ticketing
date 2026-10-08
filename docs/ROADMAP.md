# JMS One IT: Partner Support Desk. Roadmap

**Idea:** a partner company's admin or staff reports a problem (database crash, CCTV, network, server...)
instead of phoning the boss. JMS (super admin) sees it at once, assigns one of JMS's IT engineers, and
both sides can follow the progress until it is fixed and rated.

## Who does what

| Person | Account | What they do |
|---|---|---|
| Partner staff | `user` + company | Submit tickets, reply, confirm the fix and rate it |
| Partner admin | `admin` + company | Same, plus see all of their company's tickets and manage their people |
| JMS super admin | `super_admin` (no company) | Sees every partner, creates companies, **assigns JMS engineers** |
| JMS admin | `admin` marked **JMS One IT** (no partner company) | Sees every partner's tickets, accepts them and **assigns JMS engineers**; manages JMS's own engineers. No companies, reports or other admins |
| JMS engineer | `it_support` with **no company** | Works only the tickets assigned to them, across partners |
| Partner's own IT | `it_support` + company | Optional: a partner that has its own IT team can still use it |

Rules that protect both sides:
- A partner only ever sees its own company's tickets.
- The partner's admin, the super admin and JMS admins can assign JMS engineers or the partner's own IT team. Partner users never see the staff list.
- A JMS engineer only sees tickets assigned to them.

---

## Phase 1: Report to JMS, JMS dispatches (DONE)
- JMS support team: IT Support accounts with no company, assignable to any partner's ticket by a super admin.
- Engineer picker grouped into "JMS support team" and "Company IT team"; JMS engineers listed first.
- New categories: **Database**, **CCTV / Surveillance**, **Server / Infrastructure**; **Network / Internet** moved first.
- "What to include" prompt per category in the description box, so tickets arrive with the details IT needs.
- Company name shown in the ticket list and ticket details.
- JMS lock: partner admins can't take a ticket away from the JMS engineer working on it.
- Creating/editing/importing accounts supports the JMS support team (company left empty, IT Support only).
- Guard: an engineer can't be moved to another company while holding that company's active tickets.
- Demo account `jmsengineer@jmsoneit.com`. No database migration needed.

## Phase 2: Faster, richer reports
- **Attachments** (DONE): screenshots, error photos, camera snapshots, log files (JPG/PNG/GIF/WEBP, PDF, TXT/LOG/CSV; up to 5 files of 10 MB). On new tickets and on replies/internal notes; kept on the private disk and served only to people who may see the ticket. Paste a screenshot with Ctrl+V.
- **Email notifications** for new, assigned, replied and resolved (the in-app bell already exists).
- **Emergency path** for Critical tickets: show the JMS hotline on the form and alert everyone on call.
- Partner "company overview": open tickets, recently resolved, average response time.

## Phase 3: Service levels and accountability
- **Per-partner SLA** (response and resolution targets per priority, business hours vs 24/7).
- Escalation when a ticket is about to breach its SLA (notify the super admin).
- **On-call rota** for JMS engineers; auto-suggest the engineer on duty.
- Monthly **report per partner**, emailed automatically (PDF/Excel exports already exist).

## Phase 4: Know the partner's systems
- **Asset register** per partner: servers, database instances, NVR/DVR + cameras, network gear
  (location, IP, serial, warranty); pick the affected asset on the ticket.
- **Recurring maintenance** (monthly backup check, CCTV health check) that creates scheduled tickets.
- Knowledge base / runbooks for common fixes; secure vault for remote-access details.

## Phase 5: Scale and polish
- Two-factor sign-in, SSO.
- Mobile-friendly install (PWA) and push notifications for engineers in the field.
- Time tracking and billing/contract reports.
- API / webhooks for monitoring tools to open tickets automatically.
