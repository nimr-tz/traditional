What's new in v2.1.0: Added Hybrid USSD Application — combines internal (mGov Portal) and external (your backend) menu processing within the same session using header_type = 4.
USSD Documentation
This guide provides technical instructions for integrating external applications with the mGov USSD Gateway. Public institutions can use this gateway to deliver mobile services through session-based USSD codes such as *152*00#, which work on all mobile phones—smart or basic.

USSD Overview
mGov supports two integration approaches for institutions:

Externally Hosted USSD Application: Your backend handles all menu logic and responses. The mGov Portal sends session data via HTTP POST to your registered forward URL.
mGov-Hosted USSD Application: Menus are built using the mGov Portal interface. No code is required—logic and options are configured using menu blocks and templates.
1. Externally Hosted USSD Application
Steps to Integrate
Log into the mGov Portal: Use your account credentials to access the portal.
Navigate to USSD Management: From the main menu, go to USSD Management.
Add a New USSD Application: Go to the USSD Applications section and click on "Add App".
Fill Application Details: Provide the Application name and select a shortcode (default is *152*00#).
Configure Menu Blocks: In the Menu Builder, create one or more menu blocks and assign a keyword to each.
Set the Forwarding URL: Under Advanced Menu Settings, provide the full forward URL to your backend (e.g. https://yourdomain.go.tz/ussd-menu-handler).
Test Your Application: Use the built-in USSD simulator to verify menu navigation, validations, and responses.
Publish the Application: Once tested and approved, publish your Application to go live on the USSD platform.
USSD Request Structure (mGov Portal → Institution)
When a user initiates the USSD session by dialing your shortcode, our system will make an initial request to your endpoint with the following parameters:

Example:

 GET $URL?msisdn=255715510906&sessionid=12145&lang=(EN/SW) 
Subsequent Requests
For each user input during an active USSD session, our system will send a new request containing:

Example:

GET $URL?msisdn=255715510906&sessionid=12145&input=user_input"
Parameter Descriptions
Parameter	Description	Example
sessionid	Unique session identifier	234WES3390HYUI
msisdn	This is the citizen’s mobile number, the one who made the request.	255788010203
input	This is the message content that was sent by Mobile network operator (MNO)	*152*00#


Expected Response from Institution
Your system should respond with a JSON object that defines what the user will see next. This includes the menu title and selectable options.

Preview:

{
  "header_type": "2",
  "text": "Karibu huduma za Serikali",
  "options": {
    "1": "Huduma A",
    "2": "Huduma B"
  }
}  
For termination:

 {
  "header_type": "3",
  "text": "Ombi lako limepokelewa",
  "options": {}
}
Field	Description	Example
header_type	2 = expects user input
3 = end session	2
text	Menu or message title shown to the user	Karibu huduma za Serikali
options	Map of numeric options and their labels	"1": "Huduma A", "2": "Huduma B"
2. mGov Portal Hosted Applications
If you prefer a no-code solution, you can create, manage, and publish USSD menus directly through the mGov Portal using the Menu Builder tool.

In-Menu Detail Request
When a user submits input in a menu that requires validation or lookup (e.g. NIDA, TIN, phone number), the mGov Portal sends that data to your system using a POST request.

Request Payload Preview:

{
  "data": "ID2345678"
}
Field	Description	Example
data	The value entered by the user in the menu (e.g., an ID number)	12345678

Expected Response from Your System:

{
  "success": true,
  "detail_01": "Ally Ismail",
  "detail_02": "Male",
  "detail_03": "35"
}
Field	Description	Example
success	Whether the lookup was successful	true if success and false if fail
detail_01	First piece of information (e.g., name)	Ally Ismail
detail_02	Second piece of information (e.g., gender)	Male
detail_03	Additional data (e.g., age, ID type, etc.)	35
Session Summary (Final POST from mGov)
After a user completes a USSD session, the mGov platform sends a final summary POST request to your server. This summary contains all inputs the user made throughout the session, structured as key-value pairs based on your menu keywords.

Example :

{
  "gender": "Male",
  "region": "Kagera",
  "name": "Ismail Ally",
  "confirm": "1"
}
Sample Payload from mGov
Field	User Input
gender	Male
region	Kagera
name	Mshakangoto
confirm	1
Expected Response from Your System
After receiving the session summary, your server should respond with a JSON object indicating whether the operation succeeded and whether the user should receive a confirmation SMS.

{
  "status": true,
  "sms_reply": true,
  "sms_text": "Ombi lako limepokelewa kwa mafanikio. Kumbukumbu: RT234566710."
}
Sample Response
Field	Value	Description
status	true	Indicates whether the processing was successful
sms_reply	true	Whether mGov should send an SMS response to the user
sms_text	Ombi lako limepokelewa. Kumbukumbu: RT234566710.	The SMS message to be sent (if sms_reply is true)

3. Hybrid USSD Application (Internal & External)
The Hybrid USSD Application combines both internally hosted (mGov Portal) and externally hosted (your backend) menu processing within the same session. This approach gives you the flexibility to handle simple flows inside the mGov Portal while delegating complex logic — API calls, validations, data collection — to your backend, then seamlessly return control to the internal menus without ending the session.

How It Works
The user starts with an internal menu configured in the mGov Portal.
A menu option routes the request to your external backend.
Your backend processes the request (validations, API calls, data collection).
Your backend either:
Continues handling the session externally (header_type = 2)
Ends the session (header_type = 3)
Returns control back to the internal menu (header_type = 4)
Flow:

Internal Menu → External Processing → Return to Internal Menu → Continue Flow
Header Types
header_type	Meaning
2	Continue session — external handling continues, expects user input
3	End session — terminates the USSD session
4	Return control to internal menu (mGov Portal) — session continues internally
External Menu Response (Continue Flow)
When your backend needs to continue handling the session externally, respond with header_type = 2, exactly as in a standard externally hosted application:

{
  "header_type": "2",
  "text": "Karibu huduma za Serikali",
  "options": {
    "1": "Huduma A",
    "2": "Huduma B"
  }
}
Returning Control to Internal Menu (header_type = 4)
When external processing is complete and the session should resume using mGov Portal internal menu logic, respond with header_type = 4:

{
  "header_type": "4",
  "text": "Data captured successfully",
  "data": {
    "region": "Arusha",
    "district": "Longido"
  }
}
Field	Type	Description	Example
header_type	string	Must be "4" to return control to the internal menu	"4"
text	string	Message tells about the response. Not vissible to user	"Data captured successfully"
data	object	Key-value pairs collected during external processing. Passed back to the internal system for pre-filling fields, conditional logic, or final submission.	{"region":"Arusha","district":"Longido"}
Important Notes
header_type = 4 is only valid in Hybrid integrations.
The data object must be valid JSON — keep the payload lightweight.
The session is not terminated when header_type = 4 is returned.
When to Use the Hybrid Approach
Some flows are simple (internal menus) while others require external API integration.
You need real-time validation (e.g. NIDA, TIN lookups) mid-session before resuming portal menus.
You want to maintain portal-level control while still supporting complex backend logic.
Best Practices
Keep menus within the 160-character limit
Design flat, simple navigation
Provide clear options, instructions, and validations
Support Swahili and English if applicable
Testing Tools
Use the USSD Simulator in the mGov portal to test your Application using real-world scenarios before go-live.

Copyright © 2026 eGA.