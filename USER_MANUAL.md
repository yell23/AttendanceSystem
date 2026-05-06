# AttendQR System - User Manual

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Dashboard Overview](#dashboard-overview)
4. [Core Features](#core-features)
   - [Events Management](#events-management)
   - [QR Code Generation](#qr-code-generation)
   - [QR Code Scanner](#qr-code-scanner)
   - [Attendees Management](#attendees-management)
   - [Google Forms Integration](#google-forms-integration)
5. [Reports & Analytics](#reports--analytics)
6. [Settings & Configuration](#settings--configuration)
7. [Frequently Asked Questions](#frequently-asked-questions)
8. [Troubleshooting](#troubleshooting)
9. [Best Practices](#best-practices)

---

## Introduction

**AttendQR** is a powerful QR Code-based attendance tracking system designed to streamline event management and attendee registration. It combines QR code technology with Google Forms integration to provide a seamless, modern attendance solution.

### Key Features

- ✅ **QR Code Generation** - Automatically generate unique QR codes for each event
- ✅ **Real-time Scanning** - Scan QR codes using any device with a camera
- ✅ **Event Management** - Create, edit, and manage events effortlessly
- ✅ **Google Forms Integration** - Embed and manage Google Forms for registration
- ✅ **Attendee Tracking** - Maintain detailed records of attendees and attendance
- ✅ **Analytics & Reports** - Generate comprehensive reports with visual analytics
- ✅ **Secure Authentication** - User login with role-based access control
- ✅ **Audit Logs** - Complete activity tracking for compliance and transparency

---

## Getting Started

### System Requirements

- **Web Browser:** Chrome, Firefox, Safari, or Edge (latest versions recommended)
- **Camera:** For QR code scanning functionality
- **Internet Connection:** Required for Google Forms integration
- **JavaScript:** Must be enabled in your browser

### Initial Login

1. Open your web browser and navigate to your AttendQR installation
2. Enter your login credentials:
   - **Email:** Your registered email address
   - **Password:** Your password
3. Click **Login**
4. You will be redirected to the Dashboard

> **🔒 Security Tip:** Change your default password immediately after first login.

### Changing Your Password

1. Go to **Settings** in the main menu
2. Navigate to the **Password** section
3. Enter your current password
4. Enter your new password twice for confirmation
5. Click **Update Password**

---

## Dashboard Overview

The Dashboard is your main hub for monitoring system activity and quick access to key features.

### Dashboard Components

#### 1. **Key Statistics**
- Total Events Created
- Total Attendees Registered
- Today's Check-ins
- Pending Notifications

#### 2. **Quick Action Buttons**
- **Create New Event** - Quickly create a new event
- **Generate QR Codes** - Jump to QR code generation
- **Open Scanner** - Start scanning attendance
- **View Reports** - Access analytics and reports

#### 3. **Recent Activity Feed**
- Lists recent check-ins
- Shows upcoming events
- Displays system notifications

#### 4. **Charts & Visualizations**
- Attendance trends over time
- Events vs. Attendees comparison
- Daily check-in statistics

### Navigation Menu

The left sidebar provides easy access to all main features:

- **Dashboard** - Main overview page
- **Events** - Create and manage events
- **QR Codes** - Generate QR codes for events
- **Scanner** - Real-time attendance scanning
- **Attendees** - View and manage attendee records
- **Forms** - Manage Google Forms integration
- **Reports** - View analytics and generate reports
- **Logs** - View system activity logs
- **Settings** - Configure system preferences
- **Notifications** - View system messages

---

## Core Features

### Events Management

Events are the foundation of the AttendQR system. Each event can have multiple attendees and associated QR codes.

#### Creating a New Event

1. Click **Events** in the sidebar
2. Click **+ Create New Event** button
3. Fill in the following details:
   - **Event Name** - Name of the event (required)
   - **Description** - Event details and description (optional)
   - **Date** - Event date (required)
   - **Time** - Event start time (required)
   - **Location** - Physical or virtual location (optional)
   - **Capacity** - Maximum number of attendees (optional)
   - **Status** - Active/Inactive

4. Click **Create Event**

#### Editing an Event

1. Go to **Events**
2. Find your event in the list
3. Click the **Edit** button (pencil icon)
4. Update the necessary information
5. Click **Save Changes**

#### Deleting an Event

1. Go to **Events**
2. Find your event in the list
3. Click the **Delete** button (trash icon)
4. Confirm the deletion

> **⚠️ Warning:** Deleting an event will also delete all associated QR codes and attendance records.

#### Viewing Event Details

1. Go to **Events**
2. Click on the event name or **View Details** button
3. You can see:
   - Event information
   - Associated QR codes
   - Registered attendees
   - Attendance statistics

---

### QR Code Generation

QR codes are unique identifiers that attendees scan to mark their attendance.

#### Generating QR Codes

1. Navigate to **QR Codes** in the sidebar
2. Click **+ Generate QR Codes**
3. Select the event from the dropdown menu
4. Choose the number of QR codes to generate
5. (Optional) Customize:
   - QR code size
   - Error correction level
   - File format (PNG, JPEG, SVG)

6. Click **Generate**

#### Downloading QR Codes

1. Go to **QR Codes**
2. Find the generated QR codes in the list
3. Click **Download** to download individual codes
4. Or select multiple and click **Download All**

#### Printing QR Codes

1. After generating, click **Print** button
2. Configure print settings in your browser's print dialog
3. Arrange QR codes per page (recommended: 6-9 codes per page)
4. Print to PDF or physical printer
5. Cut and distribute to attendees

#### QR Code Best Practices

- Generate codes at least 2-3 days before the event
- Print at minimum 100mm × 100mm size for reliable scanning
- Use high contrast (black on white) for best results
- Test codes with your scanner app before the event
- Keep backup digital copies

---

### QR Code Scanner

The scanner is used during events to mark attendee check-ins in real-time.

#### Accessing the Scanner

1. Click **Scanner** in the sidebar
2. Grant camera access when prompted
3. The camera feed will appear

#### Scanning Process

1. **Select Event** - Choose the event from the dropdown
2. **Position Camera** - Point at the QR code
3. **Automatic Detection** - The system automatically detects and scans
4. **Confirmation** - A success message appears when code is scanned
5. **Continue Scanning** - The scanner is ready for the next code

#### Scanner Features

- **Attendance Log** - View list of scanned attendees in real-time
- **Undo Last Scan** - Remove the last scanned attendance
- **Manual Entry** - Manually search and mark attendance
- **Sound Notification** - Audio alert for successful scans (can be toggled)
- **Statistics** - See current check-in count

#### Scanner Tips

- Ensure good lighting conditions
- Keep QR codes at a reasonable distance (10-30cm from camera)
- Hold camera steady while scanning
- Test scanner on a few codes before the event
- Keep a backup method (manual entry) available

---

### Attendees Management

Manage and track all event attendees and their attendance records.

#### Viewing Attendees

1. Go to **Attendees** in the sidebar
2. You can see a list of all registered attendees with:
   - Name
   - Email
   - Event attended
   - Check-in status
   - Check-in time
   - Registration date

#### Adding Attendees Manually

1. Go to **Attendees**
2. Click **+ Add Attendee** button
3. Fill in:
   - **First Name** (required)
   - **Last Name** (required)
   - **Email** (required)
   - **Event** (required)
   - **Special Notes** (optional)

4. Click **Add Attendee**

#### Editing Attendee Information

1. Go to **Attendees**
2. Find the attendee in the list
3. Click **Edit** (pencil icon)
4. Update the information
5. Click **Save**

#### Marking Attendance

1. Go to **Attendees**
2. Click the attendee's name
3. Click **Mark Present** to check them in
4. Click **Mark Absent** if they didn't attend
5. Add notes if necessary

#### Exporting Attendee Data

1. Go to **Attendees**
2. Click **Export** button
3. Choose format:
   - CSV (for Excel/Google Sheets)
   - PDF (for printing/archiving)
   - JSON (for data integration)

4. File will download to your computer

#### Searching & Filtering

1. Use the search bar to find attendees by:
   - Name
   - Email
   - Event name
2. Filter by:
   - Attendance status (Present/Absent)
   - Event
   - Date range

---

### Google Forms Integration

Embed and manage Google Forms for event registration and data collection.

#### Embedding a Google Form

1. Go to **Forms** in the sidebar
2. Click **+ Embed New Form**
3. Provide:
   - **Form Name** - Name for reference
   - **Form URL** - Your Google Form's shareable link
   - **Associated Event** - Link to an event (optional)

4. Click **Embed Form**
5. The form will display on the page for attendees to fill

#### Getting Your Google Form URL

1. Open your Google Form in Google Forms
2. Click **Share** button (top right)
3. Copy the shareable link
4. Use this link in AttendQR

#### Managing Forms

1. Go to **Forms**
2. View all embedded forms
3. Click **View Form** to open it
4. Click **Edit** to update form details
5. Click **Delete** to remove the form

#### Form Response Integration

- Form responses are captured when attendees submit
- Responses are automatically linked to attendees
- View response data in the attendee profile
- Export responses along with attendance data

---

## Reports & Analytics

Generate comprehensive reports to analyze attendance patterns and event performance.

#### Accessing Reports

1. Click **Reports** in the sidebar
2. Choose report type:
   - **Attendance Summary** - Overall attendance statistics
   - **Event Analytics** - Per-event analysis
   - **Attendee Analytics** - Individual attendee tracking
   - **Daily Trends** - Attendance over time
   - **Custom Report** - Build your own report

#### Attendance Summary Report

Shows:
- Total events
- Total attendees
- Overall attendance rate (percentage)
- Average attendance per event
- Top attended events

#### Event Analytics Report

For each event, shows:
- Total registrations
- Actual attendance count
- Attendance rate
- Check-in times
- Peak check-in hours
- No-show list

#### Viewing Charts & Graphs

- **Bar Charts** - Compare metrics across events
- **Pie Charts** - Show attendance vs. absence ratios
- **Line Charts** - View trends over time
- **Heatmaps** - Identify peak times

#### Exporting Reports

1. Generate your report
2. Click **Export** button
3. Choose format:
   - PDF (formatted document)
   - CSV (spreadsheet format)
   - JSON (data format)
   - Excel (spreadsheet with formulas)

4. File downloads automatically
5. Share with stakeholders or store for records

#### Custom Report Builder

1. Go to **Reports**
2. Click **Create Custom Report**
3. Select:
   - **Date Range**
   - **Events to Include**
   - **Metrics** (attendance, registration, etc.)
   - **Visualization Type**
   - **Grouping** (by event, by date, by status)

4. Click **Generate Report**

---

## Settings & Configuration

Configure system preferences and manage your account.

#### Account Settings

1. Click **Settings** in the sidebar
2. Click **Account** tab

##### Update Profile

- **First Name** - Your first name
- **Last Name** - Your last name
- **Email** - Your email address
- **Phone** - Contact number (optional)

Click **Save Profile**

##### Change Password

1. Click **Change Password** section
2. Enter current password
3. Enter new password
4. Confirm new password
5. Click **Update Password**

#### System Preferences

1. Go to **Settings**
2. Click **Preferences** tab

##### General Settings

- **System Name** - Display name for your organization
- **Time Zone** - Select your time zone
- **Date Format** - Choose preferred date format
- **Language** - Select system language

##### Notification Settings

- **Email Notifications** - Receive email alerts
- **Scanner Notifications** - Audio alert for scans
- **Event Reminders** - Get event reminders
- **Frequency** - How often to receive notifications

##### Security Settings

- **Session Timeout** - Auto-logout duration
- **Two-Factor Authentication** - Enable 2FA (if available)
- **Login History** - View recent login activities
- **Active Sessions** - Manage active sessions

Click **Save Preferences**

---

## Frequently Asked Questions

### Q: How do I reset my password?

**A:** Click "Forgot Password" on the login page, enter your email, and follow the link in the reset email. You'll receive a password reset link valid for 24 hours.

### Q: Can I generate multiple QR codes for one event?

**A:** Yes! Go to **QR Codes**, select your event, and specify the number of codes to generate. Each code is unique but links to the same event.

### Q: What happens if I delete a scanned attendance record?

**A:** The attendee is marked as absent. You can rescan the QR code to mark them present again.

### Q: How can I backup my data?

**A:** Go to **Settings**, click **Data Management**, and select **Export All Data**. This creates a complete backup of all events, attendees, and logs.

### Q: Can I edit attendance after the event?

**A:** Yes, go to **Attendees**, find the person, click their name, and click **Edit Attendance** to change their status or time.

### Q: What's the maximum number of attendees per event?

**A:** There's no technical limit, but performance may vary based on your server. Typically handles 10,000+ attendees per event.

### Q: Can I import attendees from a spreadsheet?

**A:** Yes, go to **Attendees**, click **Import**, upload your CSV file, and map the columns to match your data.

### Q: How do I share reports with others?

**A:** Generate the report, click **Share**, and copy the link. Or export as PDF and email the file.

### Q: Can QR codes be scanned with a regular smartphone?

**A:** Yes, any smartphone with a camera can scan QR codes using the built-in camera app or QR scanner apps.

### Q: What if the scanner doesn't work?

**A:** Ensure camera access is granted, test with a known working QR code, and try a different browser if the issue persists.

---

## Troubleshooting

### Scanner Not Working

**Problem:** Camera feed doesn't appear or scanner is non-responsive

**Solutions:**
1. Check browser camera permissions (Settings → Site permissions → Camera)
2. Try a different browser (Chrome recommended)
3. Restart your browser
4. Clear browser cache (Settings → Clear browsing data)
5. Check your internet connection
6. Ensure QR code is not too far (10-30cm optimal)

### QR Code Not Scanning

**Problem:** Valid QR codes won't scan

**Solutions:**
1. Ensure adequate lighting
2. Clean camera lens
3. Check code isn't damaged or faded
4. Verify code is high quality (100mm × 100mm minimum)
5. Move code closer or further from camera
6. Try rotating the device

### Attendance Not Recording

**Problem:** Scanned codes don't appear in attendance logs

**Solutions:**
1. Verify the correct event is selected in scanner
2. Check internet connection
3. Refresh the page
4. Look in **Attendees** section to confirm
5. Check system logs for errors
6. Try manual entry as alternative

### Forms Not Loading

**Problem:** Google Forms won't appear on the Forms page

**Solutions:**
1. Verify Google Form URL is correct
2. Check the form is set to "Anyone with the link can fill out"
3. Ensure Google services are not blocked in your network
4. Try embedding the form in a different browser
5. Check your internet connection

### Page Loads Slowly

**Problem:** Dashboard or reports take too long to load

**Solutions:**
1. Clear browser cache
2. Check internet connection speed
3. Try a different browser
4. Close unnecessary tabs/applications
5. Restart your browser
6. Contact system administrator if problem persists

### Can't Log In

**Problem:** Login credentials not accepted

**Solutions:**
1. Verify CAPS LOCK is off
2. Ensure you're using the correct email address
3. Try resetting your password
4. Clear browser cache and cookies
5. Try a different browser
6. Check your internet connection

### Export Files Not Downloading

**Problem:** Export button doesn't download files

**Solutions:**
1. Check browser's download permissions
2. Disable pop-up blockers
3. Try a different browser
4. Check your internet connection
5. Clear browser cache
6. Ensure your device has sufficient storage

### Permission Denied Errors

**Problem:** "You don't have permission to access" message

**Solutions:**
1. Verify you're logged in
2. Check your user role (contact administrator)
3. Verify event ownership (you may not have permission for others' events)
4. Log out and log back in
5. Try a different browser

---

## Best Practices

### Event Planning

1. **Plan Ahead** - Create events 2-3 weeks in advance
2. **Generate QR Codes Early** - Have codes ready 1 week before event
3. **Test Everything** - Test scanner, forms, and reports before event
4. **Set Capacity** - Define maximum attendees for event
5. **Create Backup** - Have manual sign-in sheet as backup

### During Event

1. **Arrive Early** - Set up scanner and forms before attendees arrive
2. **Test Scanner** - Test with 5-10 codes before attendees arrive
3. **Have Backup** - Keep manual entry form available
4. **Monitor Progress** - Check real-time statistics during event
5. **Provide Support** - Assist attendees with QR code scanning

### After Event

1. **Generate Reports** - Create reports within 24 hours
2. **Export Data** - Backup attendance and form responses
3. **Archive Records** - Store in secure location for compliance
4. **Send Confirmations** - Email attendees confirmation of attendance
5. **Analyze Trends** - Review analytics for future improvements

### Security & Compliance

1. **Change Default Password** - Immediately upon first login
2. **Regular Backups** - Export data weekly
3. **Secure Access** - Don't share login credentials
4. **Review Logs** - Check audit logs monthly
5. **Update Contact Info** - Keep email address current
6. **Comply with GDPR** - Ensure data handling meets regulations
7. **Delete Old Data** - Archive or delete data per retention policy

### QR Code Best Practices

1. **Size** - Print at least 100mm × 100mm
2. **Contrast** - Use black codes on white background
3. **Laminate** - Protect from damage at events
4. **Test** - Scan each code before distributing
5. **Distribute** - Give attendees codes in advance when possible
6. **Keep Backups** - Store digital copies

### Report Generation

1. **Timely Reports** - Generate within 24 hours of event
2. **Clear Metrics** - Focus on important KPIs
3. **Visual Presentation** - Use charts for easier understanding
4. **Compare Trends** - Track metrics over multiple events
5. **Share Insights** - Distribute reports to stakeholders
6. **Maintain Records** - Archive reports for future reference

---

## Support & Contact

For additional help or technical issues:

- **System Administrator** - Contact your IT administrator
- **Check FAQs** - Most questions are answered above
- **Review Logs** - Check system logs for error details
- **Clear Cache** - Try clearing browser cache first

---

## Version Information

- **System:** AttendQR
- **Last Updated:** April 2024
- **Status:** Production Ready

---

**Thank you for using AttendQR! We hope this system helps make your event management smooth and efficient.**

---

*This manual is a living document and will be updated as new features are added. Check back regularly for updates.*
