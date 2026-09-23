MASTER PROMPT — BARANI HYDRAULICS 3D REALISTIC ENTERPRISE PORTAL

Build a premium, production-quality enterprise web application frontend for “Barani Hydraulics”.

IMPORTANT:
This must NOT look like an AI-generated concept website, cartoon, illustration, generic dashboard, gaming UI, or futuristic sci-fi mockup.

The website must look like a REAL corporate industrial software product that could actually be used by Barani Hydraulics employees.

Use the provided Barani Hydraulics reference image as the visual and architectural reference wherever appropriate.

==================================================
1. OVERALL VISUAL STYLE
==================================================

Create an ultra-realistic, premium industrial/hydraulics theme.

Design language:
- Photorealistic industrial environments
- Realistic 3D factory architecture
- Real hydraulic machines, cylinders, CNC machines, assembly equipment and industrial components
- Dark navy / steel-blue professional color palette
- Subtle electric-blue highlights
- Premium glass panels only where appropriate
- Realistic shadows, reflections and lighting
- High-quality PBR-style materials
- Metallic surfaces
- Steel, aluminium, glass and concrete textures
- Realistic depth and perspective
- Clean enterprise typography
- Minimal and professional UI
- No excessive neon
- No cartoon graphics
- No exaggerated sci-fi elements
- No childish illustrations
- No generic AI dashboard appearance

The result should feel like:
“An actual premium digital portal built for a real hydraulic manufacturing company.”

==================================================
2. 3D EXPERIENCE
==================================================

Use real-time 3D / WebGL style experiences where appropriate.

Use:
- Three.js / React Three Fiber where suitable
- Smooth camera movement
- Cinematic transitions
- Realistic lighting
- Ambient shadows
- Depth of field used subtly
- Parallax movement
- Scroll-based camera movement
- Smooth zoom and pan
- Interactive 3D hotspots
- Micro-interactions

3D must support the user experience, NOT make the website look like a game.

Maintain excellent performance and provide optimized fallbacks for lower-end devices.

==================================================
3. FIRST SCREEN — REAL BARANI HYDRAULICS ENTRY
==================================================

The website must START at the actual Barani Hydraulics entrance.

Do NOT start with a generic login form.

Create a realistic 3D cinematic view of the actual Barani Hydraulics factory entrance.

Scene:
- Main Barani Hydraulics entrance gate
- Company logo clearly visible
- Security cabin
- Security barrier
- Factory buildings visible beyond the gate
- Realistic road
- Parking
- Industrial surroundings
- Trees and landscaping
- Realistic vehicles
- Natural daylight
- Realistic shadows
- Subtle environmental movement

Camera should behave like a real camera approaching the factory.

Animation:

Camera starts outside the factory.

Slowly move toward the entrance.

Security gate opens.

Camera passes through the entrance.

Continue toward the main facility.

Then smoothly transition into the application login screen.

This should feel like:

“Entering Barani Hydraulics.”

==================================================
4. LOGIN SCREEN
==================================================

After the entrance animation, display a premium secure login interface.

Background:
- Realistic blurred 3D factory environment
- Hydraulic machinery visible subtly in the background
- Dark transparent glass login panel

Show:

BARANI HYDRAULICS logo

“Welcome to Barani Hydraulics”

Email / Employee ID
Password
Remember me
Forgot password
Login button

Use subtle entrance animation.

Do NOT use a generic SaaS login template.

The login must visually belong to an industrial hydraulic company.

==================================================
5. AFTER LOGIN — ENTERING THE FACTORY
==================================================

When the user clicks LOGIN:

Do NOT immediately jump to a normal dashboard.

Create a cinematic transition.

The camera should appear to enter the Barani Hydraulics facility.

Example:

Login successful
↓
Camera transition
↓
Factory entrance
↓
Main lobby
↓
Production area
↓
Application interface appears

Make the user feel:

“You have entered the Barani Hydraulics digital facility.”

Use realistic 3D factory scenes.

==================================================
6. MAIN APPLICATION DASHBOARD
==================================================

After entering the facility, show the main enterprise portal.

Layout:

LEFT SIDEBAR

Barani Hydraulics logo

Home
Company Structure
Departments
Employees
Projects
Inventory
Production
Machines
Attendance
Reports
Locations / Maps
Settings

TOP BAR

Global search:

“Search employees, departments, projects, machines, reports…”

Notification icon
User profile
Role indicator

MAIN AREA

Welcome message

Key company statistics

Total Employees
Departments
Active Projects
Production Units
Machines
Pending Reports

Use clean enterprise cards.

Charts should be realistic and professional.

==================================================
7. 3D FACTORY MAP
==================================================

Create an interactive 3D digital representation of the Barani Hydraulics facility.

Show realistic factory buildings such as:

- Production Area
- Assembly Unit
- CNC / Machine Area
- Quality Control
- Warehouse
- Maintenance
- Administration
- Parking
- Security
- Dispatch

Each location should be clickable.

When clicked:

Camera smoothly moves toward that location.

Display information panel.

Example:

Production Area

Machines:
Hydraulic Press
CNC Machines
Cylinder Assembly
Testing Equipment

Status:
Operational

Employees:
XX

Production:
XX units

Use realistic 3D industrial assets.

==================================================
8. MACHINES THEME
==================================================

The entire application should visually communicate hydraulic manufacturing.

Use realistic:

- Hydraulic presses
- Hydraulic cylinders
- Pumps
- Valves
- CNC machines
- Lathe machines
- Milling machines
- Assembly lines
- Welding stations
- Testing equipment
- Industrial robots where appropriate
- Hydraulic power units
- Pipes and fittings

Machines should look like actual industrial machinery.

Avoid unrealistic futuristic machines.

==================================================
9. SATELLITE LOCATION SYSTEM
==================================================

Add a real satellite map section.

Use a proper mapping system such as:

Google Maps / Mapbox / OpenStreetMap depending on implementation.

The map should show the REAL Barani Hydraulics location.

Do NOT invent a random factory location.

Use the actual Barani Hydraulics location/address provided by the project/company data.

Provide:

Satellite View
Map View
Zoom
Pan
Location marker
Company location card
Directions
Nearby locations if available

When the user selects the company location:

Smoothly transition from satellite view → 3D / facility view.

==================================================
10. COMPANY LOCATION EXPERIENCE
==================================================

Create:

“Barani Hydraulics — Location”

Show:

- Satellite image
- Exact company location
- Address
- Contact information
- Facility image
- Open in Maps
- Get Directions

Use real geographic information where available.

Never fabricate geographic coordinates.

==================================================
11. AI EMPLOYEE DATABASE CHATBOT
==================================================

Add an AI chatbot as a core feature of the application.

The chatbot must NOT be a simple FAQ chatbot.

It should work as an AI database agent.

The company database may contain approximately 200+ entities/tables.

Users should NOT need to know:

- Table names
- Entity names
- Field names
- Database relationships
- SQL syntax
- Query syntax

Users can simply ask natural-language questions.

Example:

“Ravi oda last month attendance kudu.”

“Sales department-la absent employees yaaru?”

“Employee 105 oda manager yaaru?”

“Ravi-ku leave balance evlo?”

The AI agent should understand the request and identify the relevant database information dynamically.

IMPORTANT:
Do not hardcode hundreds of entities manually.

The backend should inspect/use database schema metadata and safely determine relevant entities, fields and relationships.

The LLM must NEVER directly execute unrestricted SQL.

Use a secure controlled query layer with validation and authorization.

==================================================
12. AI AGENT + EMAIL AUTOMATION
==================================================

The chatbot must also act as an AI agent.

Example user request:

“Ravi oda attendance report-a
manager@company.com ku
tomorrow 6 PM-ku send pannu.”

The agent should:

1. Understand the request
2. Identify employee
3. Identify required information
4. Retrieve authorized database data
5. Generate the report
6. Validate recipient email
7. Schedule the task
8. At the requested time, generate/fetch the latest report
9. Send the email
10. Record success/failure

The chatbot should show task status:

Scheduled
Processing
Sent
Failed

Example:

“Attendance report scheduled for tomorrow at 6:00 PM.”

==================================================
13. REALISTIC ANIMATIONS
==================================================

Animations must be smooth and professional.

Include:

- Factory entrance camera movement
- Security gate opening
- Camera entering facility
- Login transition
- 3D building navigation
- Machine hover animations
- Location selection camera movement
- Satellite → facility transition
- Dashboard card animations
- Sidebar transitions
- Loading states
- Report generation animation
- Email scheduling status
- Success confirmation

Avoid excessive animations.

==================================================
14. RESPONSIVE DESIGN
==================================================

The application must work perfectly on:

Desktop
Laptop
Tablet
Mobile

On mobile:

Use bottom navigation / compact sidebar.

3D scenes should automatically reduce quality when necessary for performance.

==================================================
15. AUTHENTICATION & SECURITY UI
==================================================

Include professional enterprise security UX.

Roles:

Admin
HR
Manager
Employee

Different users should only see data they are authorized to access.

Show:

Secure Session
Role
Last Login
Activity Logs

Never expose database credentials in frontend.

==================================================
16. DESIGN DETAILS
==================================================

Use:

- Premium dark navy background
- Steel blue surfaces
- White typography
- Subtle blue highlights
- Realistic glass effects
- Rounded corners but not excessive
- Fine borders
- Soft shadows
- Professional icons
- Industrial imagery
- Minimal gradients

The UI must feel:

Premium
Industrial
Professional
Realistic
Trustworthy
Enterprise-grade

==================================================
17. MOST IMPORTANT REQUIREMENT
==================================================

DO NOT make the final website look like:

❌ AI-generated artwork
❌ Gaming website
❌ Sci-fi interface
❌ Cartoon
❌ Generic admin dashboard
❌ Template website
❌ Fake 3D concept

MAKE IT LOOK LIKE:

✅ Real Barani Hydraulics digital portal
✅ Real industrial environment
✅ Realistic hydraulic machinery
✅ Real factory architecture
✅ Production-ready enterprise UI
✅ Premium 3D experience
✅ Professional corporate software

The reference image provided by the user should be treated as the visual reference for the Barani Hydraulics facility and overall layout direction.

The final experience should feel like the user is physically entering the real Barani Hydraulics facility and then accessing a secure digital control portal.

Build the frontend with clean reusable components and production-quality structure so that the real database, AI agent, authentication, email service, scheduler and APIs can be connected later.