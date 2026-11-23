# Marketplace Platform — Product Specification

> **Purpose:** Build a Laravel-based marketplace inspired by the functionality of The Starters, not its existing implementation or visual design.
>
> **Document type:** High-level product and functional specification for Claude Code.
>
> **Implementation approach:** Full product scope, delivered progressively through development phases. Business requirements below are authoritative; technical architecture and implementation details are intentionally left to Claude Code.

## 1. Product Overview

The platform connects **Clients / Brands** with **Freelancers / Starters** for professional services, projects, and scheduled online consultations. Brands discover vetted freelancers, communicate through a built-in messenger, request work, and arrange paid consultations. Freelancers present their expertise, respond to requests, propose projects to existing contacts, and manage delivery.

### Product principles

- **Backend framework:** Laravel.
- **Payments and subscriptions:** Stripe as the primary external payment platform; the marketplace does not process card details itself.
- **Calls:** Google Meet only; no built-in video conferencing.
- **Calendar availability:** Recurring schedules, exceptions, and connected external calendars.
- **UI:** Minimal, functional, responsive (desktop, tablet, mobile), with reusable components, accessible interactions, and standard loading/empty/error/success states.
- **Language:** English only for now. Users may select their time zone.
- **Currency:** USD for all prices, fees, and subscriptions.
- **No platform commission** on freelancer consultation or project payments. Subscription revenue is separate.
- **One primary account role per user:** Client, Freelancer, or Admin. No multi-role switching.
- **Brand accounts:** One Client user represents one company; no shared corporate teams in scope. Meeting guests do not become company users.

## 2. Roles, Identity, and Access

### Roles

| Role | Responsibility |
| --- | --- |
| **Client / Brand** | Find freelancers, hold a subscription, initiate requests, participate in projects, and book consultations. |
| **Freelancer / Starter** | Apply to join, maintain a reviewed profile, offer consultations/services, participate in projects, and manage bookings. |
| **Admin** | Moderate content and users, oversee operations, resolve disputes, and control platform settings. |

### Authentication and account basics

- Email/password registration and login, email verification, logout, password reset.
- Account profile and settings: name, email, password, avatar, time zone, notification preferences, account deactivation, and personal-data deletion request.
- Client registration is open. Admin accounts are provisioned separately, not publicly registered.
- Freelancer access requires an application and approval process (see §3).
- Apply permissions on the server as well as in the UI; hiding a page or CTA is not sufficient access control.

### Client subscription states

A Client has a `subscription-type` of **`free-plan`** or **`paid-plan`**. Track billing health separately, including a **`payment-restricted`** state for failed renewals. Do not treat `payment-restricted` as a third subscription type.

| Capability | Guest | Free Client | Paid Client | Payment-restricted Client |
| --- | --- | --- | --- | --- |
| Informational public pages | Yes | Yes | Yes | Yes |
| Account Dashboard | No | Yes | Yes | Yes |
| Freelancer directory | No | Preview: 6–9 matches | Full | Preview: 6–9 matches |
| Full freelancer profiles | No | No | Yes | No |
| Blog | No | List + short summaries | Full articles | List + short summaries |
| Existing conversations and project history | No | Yes, when present | Yes | Yes |
| New projects and project proposals acceptance | No | No | Yes | No |
| Project completion approval and project payment | No | No | Yes | No |
| New consultation booking | No | No | Yes | No |
| Already confirmed consultations | No | Continue under agreed rules | Yes | Continue under agreed rules |
| Subscription/billing management | No | Yes | Yes | Yes |

Additional access rules:

- Free Client's directory preview shows **6–9 freelancers** best matching their saved categories, skills, and related preferences. Cards are not navigable; clicking a card shows a **Paid Plan upgrade modal** with a payment link/action.
- A Free Client's Dashboard shows available information and a prominent upgrade CTA. Paid Client has full access.
- Free and payment-restricted Clients retain existing conversations and can view ongoing projects. They **cannot** finalize/approve completion or pay for projects until Paid access is restored.
- Free and payment-restricted Clients may continue already confirmed consultations, including applicable automatic call charges, and may use existing-call cancellation/rescheduling subject to established rules. They cannot create new bookings.
- Project data, conversation history, call history, and billing history persist across subscription changes.
- Freelancers have **free access** to their professional tools after profile approval. No Freelancer subscription or platform commission.

## 3. Registration, Applications, and Onboarding

### Client onboarding

- Simple registration, email verification, and immediate access to the appropriate Free Dashboard.
- Extended **private Brand profile**: company name/logo, description, industry, website, location, social links, contact person/contact details.
- Brand profile is visible to freelancers involved in relevant interactions, not as a public company directory.
- Capture interest/preferences (categories, skills, etc.) used for personalized directory recommendations.

### Freelancer application and profile approval

1. Freelancer submits an **extended application** with identity/contact details, specialty, skills, experience, portfolio, links/social information, and other relevant professional details.
2. Admin reviews and **approves or rejects** the application.
3. Approved applicant receives an invitation to activate their account.
4. Application data **prepopulates a draft freelancer profile**; avoid asking for the same information again.
5. Freelancer completes/edits their profile and submits it for a **second, separate profile review**.
6. Admin may approve/publish the profile or request changes; Freelancer may revise and resubmit.

Suggested profile review stages: `Draft → Pending Review → Published`, with `Changes Requested` as a return path. Application approval **does not** automatically publish the profile.

## 4. Freelancer Profiles and Services

### Freelancer profile

- Basic details: name, photo, professional title, location/time zone.
- About, specialties, categories, skills, experience and work history, website/social links.
- Portfolio / Highlights, consultations, professional Services, and eligible published reviews/rating.
- Freelancer controls editing; publication and relevant changes are subject to the agreed review process.

### Highlights / portfolio

- A Highlight is a card with a title, cover, detailed description, image gallery, videos, and external links.
- **No standalone Highlight detail page.** Clicking a card opens a modal/pop-up showing its complete content.
- Freelancer can create, edit, delete, and reorder Highlights.

### Offer types

**Consultations:** Every Freelancer has exactly two consultation types, **Free Consultation** and **Paid Consultation**. They are fixed: a Freelancer cannot add, duplicate, or delete them, only configure each one:

- **Offered:** on/off. A type that is switched off is not shown to Clients and cannot be booked; its settings are kept.
- **Length:** one duration in minutes per type (15–480).
- **Price:** the Free consultation always costs **$0**. The Paid consultation costs **at least $1.00** (USD).
- **Availability:** the days of the week it can be booked and, for each day, a start and end time. Times are in the Freelancer's profile time zone and are shown to Clients converted to their own. A window must be at least as long as the consultation.
- **Description:** short text about what the call covers.

Free and Paid are configured independently (different length, days, and hours). Changes to consultations go through the same profile review as other profile content. Booking and scheduling rules are defined in §8; a Free consultation involves no payment authorization (§9).

**Services:** Freelancer creates descriptive service offers with one of these pricing modes:

- **Fixed Price**
- **Starting From**
- **Custom Quote**

**All Services use Request Only.** A Client never buys a Service directly from a listing; the parties first discuss scope, price, and deadline through a request/proposal workflow.

## 5. Marketplace, Discovery, and Content

### Freelancer directory

- Accessible only to authenticated users and subject to subscription-level restrictions.
- Search by name, profession, skills, and keywords.
- Filters/sorting for categories, specializations, skills, price, location, time zone, review/rating, and consultation availability.
- **Favorites / Saved Freelancers** for eligible Clients.
- **Featured Freelancers**, managed by Admin.
- Ranking/recommendations may use the unified Freelancer score defined in §11, alongside search relevance.

### Blog / Resources

- Authenticated Clients can see all article listings.
- Free/payment-restricted Clients see brief summaries and an upgrade prompt instead of complete articles.
- Paid Clients can read complete articles.
- Blog content can be managed administratively.
- The Blog is **not** an unauthenticated public page in this version.

## 6. Messenger and Conversations

- Built-in private messaging between Client and Freelancer, including text, file attachments, history, read status, and new-message notifications.
- Messenger can be used **without** an existing project or consultation, subject to account access.
- Creating an initial project automatically creates the parties' conversation **if no conversation exists**; do not create duplicates.
- Project-related interactions/messages can be associated with the relevant project while preserving the main relationship conversation.
- **An existing Client–Freelancer conversation is sufficient eligibility for a Freelancer to initiate a new Project Proposal**; a completed project or consultation is not required.
- Free/payment-restricted Clients retain their existing conversations and history, even though new commercial actions are blocked.

## 7. Projects, Proposals, and Service Orders

### Ways projects begin

1. **Client-initiated Service Request:** Client requests a Service, explains the task, and agrees on the scope, price, and deadline with Freelancer.
2. **Freelancer-initiated Project Proposal:** Freelancer can propose a project to a Brand with whom they already have a conversation. Brand may accept, reject, or discuss it. A proposal becomes active only after **Brand approval** and only if Brand has full Paid access.

### Basic project management

- Project details: participants, description/scope, agreed USD price, agreed deadline, and current state.
- Support pending requests/proposals, accepted projects, work in progress, declined/cancelled projects, work submitted for completion review, requested revisions, and completed/paid projects.
- Show project status and history in both parties' Dashboards.
- No complex milestones, escrow, or full deliverables-management subsystem is required at this stage.

### Project completion and payment

1. Freelancer marks work **Completed / ready for review**.
2. Brand reviews the result and either **approves completion** or **requests revisions**, returning work to Freelancer.
3. Freelancer may resubmit after revisions.
4. After Brand approves, the project is finalized and **payment is automatically initiated through the external Stripe payment system**.
5. Project is marked Paid only after payment success is confirmed; failures receive an appropriate state/notification.

**Access restriction:** Free/payment-restricted Brands may continue viewing active work and messaging, but **cannot approve completion or initiate the resulting project payment** until subscription access is restored.

## 8. Booking, Scheduling, and Google Meet

### Availability and scheduling

- Freelancer sets a recurring weekly schedule per consultation type (Free and Paid: days and hours, see §4), plus exceptions, days off, and blocked hours.
- Freelancer connects/disconnects external calendars; busy periods are considered to prevent double bookings.
- All appointment times display correctly in the viewer's selected time zone.
- Clients must book **at least 24 hours before** the proposed start time.
- Booking requires **Manual Approval** by the Freelancer; a request is not a confirmed consultation until approved.
- Clients can select a consultation type/duration, date/time, payment method, and **Meeting Agenda** (topics/questions).

### Dashboard organization

- **Call Requests:** pending booking requests and pending rescheduling requests, including the appropriate party's decision state. In Freelancer Dashboard, a rescheduling request awaiting Brand approval remains visible here with that label.
- **Calls:** all non-pending bookings, with **List + Calendar views**, status filtering, details, Google Meet links, and history.
- Track at least `Pending`, `Confirmed`, `Declined`, `Cancelled`, `Completed`, and `No-show` as appropriate. Rescheduling status must be distinguishable from the currently effective booking.

### Pending request expiration

- An unanswered initial Call Request is **automatically declined 1 hour before the scheduled start**.
- The expiration threshold must be **Admin-configurable**, defaulting to **1 hour**.
- If Freelancer fails to respond before automatic expiration, deduct **5,000 score points** from that Freelancer (see §11).
- Expiring an unanswered request is **not** the same as Freelancer explicitly declining it, Client cancellation, or a payment-authorization failure.
- No funds are reserved or charged for a **pending, unconfirmed** call.

### Cancellation rules

- **Brand cancels at least 24 hours before start:** no consultation charge.
- **Brand cancels less than 24 hours before start:** full consultation charge.
- **Freelancer cancels at any time:** Brand is not charged.
- Handle unattended/no-show meetings consistently with these responsibility rules, and permit Admin to intervene in exceptional cases (e.g., severe emergencies or war-related disruption).
- Record cancellation reasons and relevant status history; process charge/cancellation/refund decisions through Stripe.

### Rescheduling

- Either party may request a new time **only when the existing call is at least 48 hours away**; no rescheduling less than 48 hours before the call.
- The **non-initiating party must approve** the proposed time.
- The request becomes `Pending` and appears in **Call Requests**; identify which party must respond.
- **Keep the original confirmed time slot reserved and effective until the new time is approved.** If the request is declined, the original booking remains confirmed. If approved, the new time replaces it.
- Reconcile calendar availability, related events, and payment authorization when the new time is accepted.

### Google Meet consultations

- Consultations occur **only via Google Meet**. Generate/manage access to the meeting for confirmed calls; participants can access details from their Dashboards and related notifications/calendar events.
- **Guest Invitations:** allow additional meeting participants.
- **AI Notetaker:** produce consultation notes, summary, and key takeaways. Built-in video-recording storage is **out of scope**.
  - Implemented through a **third-party meeting-bot notetaker (Fireflies or Fathom; final provider to be selected during Phase 4)** that joins the Google Meet call as a participant. The platform does not transcribe audio itself.
  - The bot must be added automatically to every confirmed call (e.g., via the Meet link or the provider's API), and participants must be informed that an AI notetaker will join.
  - Notes, summary, and key takeaways returned by the provider are stored/linked to the call and shown in both parties' Dashboards.
  - **The bot also serves as a call-end signal** (e.g., bot leaves / meeting-ended or transcript-ready webhook). See "Call-end detection" below and §9.
- **Call-end detection:** the platform uses the notetaker bot's events as the primary signal that a consultation ended early. Provider webhooks may be delayed or missing (a "notes ready" webhook can arrive minutes after the call), so the scheduled end time always remains the fallback trigger. Any bot signal must be idempotent, verified (signed webhook), and matched to the correct call. The provider's actual capabilities (real-time end event vs. post-processing event, free-plan limits, API availability) must be investigated before relying on it.
- **Meeting Agenda:** Client can specify the discussion topics/questions.
- Final completion and payment triggers are in §9. Technical feasibility of accurately detecting the end of a Google Meet session must be investigated, with a safe fallback that does not create duplicate charges.

## 9. Consultation Payment Rules (Stripe)

### General model

- **A Free consultation (price $0) involves no payment method, authorization, or charge**; all payment rules below apply to Paid consultations only. Cancellation and no-show rules still apply, but have no monetary effect on a Free consultation.
- **All amounts are in USD.** Client can save **multiple payment methods without a product-imposed numeric limit** and choose a default.
- When booking a consultation, clearly display the default payment method and allow choosing a different saved method **for that specific call**.
- Validate/store the selected payment method when the booking request is created. This is **not** a reservation or a guarantee of future funds.
- **Never reserve or capture money for an unconfirmed Call Request.** The booking confirmation and subsequent authorization sequence must honor that rule.
- All actual card operations, captures, cancellations, and refunds occur through Stripe, not through the application itself.

### Authorization timing

| Situation | Requirement |
| --- | --- |
| Request created **more than 48 hours** before call | Save/validate payment method without holding funds. If Freelancer has confirmed the call, reserve funds **48 hours before start**. |
| Request created **24–48 hours** before call | Require a usable selected payment method before request submission; **attempt the full authorization immediately upon Freelancer approval**, not while pending. A consultation must not proceed as confirmed without resolving an authorization failure. |
| Freelancer approves when start is **within 48 hours** | Authorize the full amount immediately on approval. |
| A previously confirmed call reaches **48 hours before start** | Automatically reserve the full price against the selected call-specific method. |
| Authorization fails at the 48-hour check | Notify Brand by email and allow **12 hours** to correct payment. If unresolved, automatically decline/cancel the call and notify both parties. |

- A successful payment-method setup **cannot prove future sufficient funds or definitively prove that a card is not stolen**. Stripe's capabilities and fraud safeguards determine what can be verified without placing a hold.
- Handle late approvals, insufficient funds, expired methods, authorization expiry, and payment-method changes safely. **Do not represent a pending call as paid or financially guaranteed.**

### Capture timing and completion

For a confirmed consultation, charge at the **earlier** of:

1. A reliable signal that the **Google Meet consultation ended early** (primary source: the AI notetaker bot's call-end event, see §8), or
2. Its **scheduled end time**, even if the meeting runs longer.

When the applicable charge succeeds, update booking/payment states and notify Brand and Freelancer of successful completion/payment. **Capture at most once per call**, even if both triggers fire. Notify appropriately on a payment failure; do not send a payment-success notice before confirmation.

### Consultation exceptions and recovery

- Cancellation charges follow §8, including late Brand cancellation and free Freelancer cancellation. These rules take precedence over generic completion triggers.
- When a confirmed call's 48-hour authorization fails, the **12-hour payment recovery** workflow applies.
- The actual booking and payment provider integrations must account for provider limitations, especially when a payment failure or a late approval occurs close to the call start.
- Admin may resolve extraordinary disputes and initiate permitted corrections/refunds through Stripe with an audit trail.

## 10. Subscriptions and Billing

### Plans

| Plan | Price | Billing | Access |
| --- | ---: | --- | --- |
| **Free** | $0 | No recurring charge | Restricted content/actions |
| **Paid Monthly** | **$120** | Recurring monthly | Full Client access |
| **Paid Yearly** | **$1,000** | Recurring yearly | Same access as Monthly |

- Subscription billing uses **Stripe**. A successful subscription checkout/renewal automatically updates effective access and `subscription-type` to `paid-plan`.
- Support Monthly ↔ Yearly plan switching, renewal, cancellation, billing history, and payment-method management.
- Cancelling renewal retains paid access **until the already-paid period ends**, then changes `subscription-type` to `free-plan` and applies Free restrictions.
- A failed recurring payment triggers a **`payment-restricted` billing state**, appropriate notices and payment recovery, and the same core functional restrictions as Free. Restoring successful payment automatically restores Paid access.
- Do not delete conversations, active project records, confirmed calls, or billing history when a subscription lapses or enters restricted state.
- **Subscription payments are distinct from project/consultation charges.** No platform percentage fee is levied on Freelancer earnings; provider processing fees are separate.
- Freelancer payouts are handled by the external payment system, with earnings/payment/payout history visible as appropriate.

## 11. Reviews, Reports, and Unified Freelancer Score

### Public reviews

- **Only Clients review Freelancers publicly** after a real completed consultation or project.
- Review includes a **1–5 star** rating and optional written feedback.
- **Every review requires manual Admin approval before publication** and before affecting public ratings/score. Rejected reviews are not published.
- Freelancers cannot directly edit/remove reviews left about them.

### Private reports about Brands

- Freelancer may submit a **private complaint/report to Admin** concerning a Brand, including description, linked collaboration, and evidence.
- Reports are not public reviews of Brands. Admin can dismiss, warn, restrict/suspend, or delete offending Brand accounts; record decisions and reasons.

### Combined rating / score rules

Use **one unified Freelancer score** incorporating feedback, activity, and reliability; use it for marketplace ranking. Collect star ratings in reviews, but do not treat the 1–5 input alone as the overall ranking system.

| Event | Score change |
| --- | ---: |
| Admin-approved review with **1–3 stars** | **−5,000** |
| Admin-approved review with **4 stars** | **+1,000** |
| Admin-approved review with **5 stars** | **+5,000** |
| Freelancer-initiated proposal **accepted by Brand** | **+500** |
| Freelancer responds substantively to a Project Request in **less than 2 hours** | **+1,000** |
| Project successfully completed, Brand-approved, and paid | **+ project price amount in USD** (e.g. a $1,250 project earns **+1,250**) |
| Freelancer leaves a Call Request unanswered until automatic expiry | **−5,000** |

- Score each eligible event **only once**; no award merely for submitting an unaccepted proposal.
- Keep an auditable history of score events and allow authorized Admin corrections with reasons.
- Prevent duplicate rewards and obvious score manipulation.

## 12. Notifications

- **Email + In-App Notification Center**; no SMS or browser push required in this version.
- Real-time in-app indicators, read/unread states, notification history, and user notification preferences.
- Notify relevant parties about applications/moderation, messages, project requests/proposals, project review/revisions, bookings/changes/cancellations, payment authorization/recovery, successful or failed payments, subscription events, and support/disputes.
- Send reminders for upcoming consultations.

## 13. Admin Panel — Full System Control

Admin has broad operational and system permissions, including:

- User management: inspect, edit, restrict, suspend, delete, and administer access.
- Freelancer application approval/rejection and profile moderation/publication.
- Manual review approval/rejection, investigation of private reports, disputes, and sanctions.
- View/manage projects, proposals, consultations, cancellations, and exceptional situations.
- Monitor Stripe-related payment/subscription states; perform **provider-supported** payment corrections/refunds.
- Manage categories, specializations, featured freelancers, content/Blog, and relevant platform settings.
- Manage rating-point adjustments and booking-request expiration policy (default: **1 hour**).
- Maintain **Audit Logs** for sensitive Admin actions, including actor, action, subject, time, and reason.

Full System Control means broad authorized **application** administration, not bypassing Stripe's control of real funds, fraud protections, or payment-provider constraints.

## 14. Pages, Dashboard, and User Experience

### Unauthenticated public pages

- Homepage
- Become a Freelancer (application entry)
- How It Works
- About Us
- Contact / Support
- FAQ
- Legal: Terms of Service, Privacy Policy, Cancellation & Refund Policy

**Not public:** Freelancer Directory, full Freelancer Profiles, Blog/Resources. Login, registration, account activation, and password recovery are accessible when needed.

### Role-based Dashboards

**Client:** subscription/billing status, personalized freelancer recommendations, Favorites, Call Requests, Calls (list/calendar), projects/proposals, messages, notifications, account and payment-method settings. Show upgrade CTA for Free users.

**Freelancer:** profile/review status, Highlights, Services, incoming requests/proposals, active projects, Call Requests, Calls (list/calendar), availability, connected calendars, messages, earnings/payment history, reviews/score, and private reports.

**Admin:** users, applications, profile/review moderation, reports/disputes, projects, consultations, payment/subscription monitoring, categories, featured users, content, settings, audit logs.

### UX standards

- Minimal functional interface; no heavy animations or decorative requirements.
- Responsive layouts and consistent reusable components.
- Clear permissions, form validation, empty/loading/error/success states, and accessible navigation.
- All interface copy in **English**. Honor the user's selected time zone; use USD for monetary display.
- **All times are shown and entered in 24-hour format** (`HH:MM`, for example `09:00` or `17:30`) everywhere in the product, including emails and the admin area. No AM/PM.

## 15. Development Phases

The **full scope above is required in the final product**. These phases define delivery order, not permission to drop later requirements. Claude Code should break each phase into manageable tasks and verify its behavior before progressing.

### Phase 1 — Foundation

- Set up Laravel project and choose suitable frontend, persistence, auth, and testing architecture.
- Implement roles, authentication, basic account/profile settings, and role-based Dashboard shells.
- Implement Client onboarding and extended private Brand profiles/interests.
- Implement extended Freelancer applications, Admin review, activation, prepopulated draft profiles, second-stage profile review.
- Establish base Admin tools, permissions, and audit logging.

### Phase 2 — Marketplace & Subscriptions

- Freelancer profile content, Highlights modal galleries, Services and consultation listings.
- Authenticated Freelancer directory, search/filters, ranking display, Favorites, and Featured Freelancers.
- Blog/Resources with free previews and paid full content.
- Stripe recurring Monthly/Yearly subscriptions, Free/Paid/payment-restricted access rules, upgrade prompts, billing history, and recovery.

### Phase 3 — Projects & Communication

- Messenger, attachments, read states, notifications, and conversation-based eligibility.
- Client Service Requests, Freelancer Project Proposals, acceptance, pricing/deadlines, and basic project tracking.
- Freelancer completion submission, Brand approval or revisions, history, and subscription-based restrictions.
- Initial rating events, feedback submission/moderation, and Brand report workflows.

### Phase 4 — Scheduling & Consultations

- Weekly availability, exceptions, connected calendars, and time-zone handling.
- Call Requests vs Calls, Manual Approval, pending expiration, cancellation/rescheduling, calendar/list management.
- Google Meet links, guest invitations, agendas, and AI Notetaker (Fireflies/Fathom bot) functionality, including bot-based call-end detection.
- Prepare call authorization/payment event points without exposing a booking flow as financially complete until Phase 5 is integrated and verified.

### Phase 5 — Payments, Automations & Finalization

- Stripe consultation authorization at the agreed time, failure recovery, capture triggers, late-cancellation charges, and safe one-time charge enforcement.
- Automated project payment after Brand approval and relevant payout visibility.
- End-to-end email/in-app notifications, review scoring, dispute/refund operations, and full Admin capabilities.
- Validate critical journeys across subscription levels, time zones, failures, overlapping triggers, approvals, refunds, and moderation.

## 16. Guidance for Claude Code

1. **Use this specification as the source of truth for product behavior.** Do not recreate The Starters' legacy Webflow/Xano/Memberstack/Make/Nylas architecture merely because it inspired the product.
2. **Autonomously select the Laravel-compatible architecture and libraries.** Laravel, Stripe, Google Meet, and external-calendar capabilities are fixed product-level decisions; other technologies and provider details are yours to evaluate.
3. Explain significant architecture decisions briefly before implementing them, but do **not** require approval for each normal technical choice.
4. Work **phase by phase**, creating a clear task plan and testing each completed increment. Keep integrations modular and business rules configurable where specified.
5. Respect the boundaries between **subscription billing**, **project payments**, and **consultation authorizations/captures**. Never equate UI success with confirmed provider payment success.
6. Investigate provider limitations for **Google Meet end detection** (including the Fireflies/Fathom notetaker bot as the early-end signal), **payment-method verification without a hold**, **timed Stripe authorizations**, and **late-approval recovery**. Choose practical implementations/fallbacks that preserve the specified behavior as closely as possible. Document any **unavoidable product trade-off** rather than silently changing a business rule.
7. Build authorization/security, auditability, validation, idempotency, and failure handling into sensitive workflows. Protect personal data and leave card credentials with Stripe.
8. Keep the product functional specification separate from detailed code structure, database schemas, deployment decisions, and third-party implementation choices. Produce those as separate planning artifacts when useful.

---

**Status:** Product scope and development phases confirmed. Detailed architecture, integration feasibility, implementation plans, and operational edge cases belong to Claude Code's design process; the confirmed business rules must not be silently weakened.
