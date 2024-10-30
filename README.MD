# Instant Design Tool - Wordpress
  This is the general repository for the Instant Design Tool Wordpress Plugin.
  Below you will find some installation instructions, to get you up and running.
  
### Requirements
- A working WordPress installation
- Woocommerce plugin for Wordpress
- An Instant Design Tool account (with access to management panel)

### Steps to Take
- Clone the project to a local machine
- Zip the project into a folder
- Make sure the file has a .zip extension for this is the only format Wordpress will work with
- Once zipped, login to the wordpress backend
- Go to Plugins > Add New > Upload and browse to (or drag and drop on the button) the .zip file
- Press "Install now"
- After the plugin has been installed, go to the plugins tab and activate the plugin
- A notice will now appear indicating you have not synced the settings
- Follow the link in the notice which will take you to the administrative panel for the plugin
- Here you will see a couple of fields.

| Field | Description |
|:-----:|:-----------:|
| IDT API Key                                           | The value in this field will be automatically generated and will be sent with every request to the editor to authenticate the request|
| IDT Connect code                                      | In this field you will have to supply a token generated in the editor management-panel. This code will sync some settings the editor needs to function properly.   |
| Use the custom texts for buttons?                     | This setting is used to determine if custom button texts should appear on the customer-end of the website. The buttons will appear on the shop page and the product page and have respective options for those pages            |
| Editable product button text                          | This setting will determine the custom text on the shop page buttons for editable products            |
| Editable product button text on the product page      | This setting will determine the custom text on the product page buttons for editable products            |
| Use The Print API?                                    | This is the usage setting for the Print API, which can be set to: never, optionally or always|
| Print API client_id                                   | This is the client_id for the Print API, which you can find in the Print API dashboard            |
| Print API secret                                      | This is the secret for the Print API, which you can find in the Print API dashboard            |
| Print API environment                                 | This option is used to determine the environment the Print API is working in. If you are using development keys, please specify test.|
| Print API status                                      | This option is used to indicate the status of the Print API. If all is well and specified, this should show connected.|


### Print API usage modes
The Print API offers three different usage modes:
- Never
    - This usage mode will never use the Print API. 
- Optionally
    - This usage mode will offer the option to forward orders to the Print API.
      That means, it will display a meta-box on the order page, and offer you the ability to send all the editable items on this order to the Print API, 
      but will only do so when you implicitly order the plugin to do so.
- Always
    - This option will forward any orders editable items to Print API automatically.
    - This option is to be used with great caution. Due to the fact that product id's might differ from platform to platform it is advised to test thoroughly before enabling this setting in production.
    - Product id mismatches **will** result in an exception which is very hard to circumvent.


### Debugging the plugin
To debug the workings of the plugin, and get insight into errors that might pop up, several small steps are required.

Note:  It is a bad practise to follow these steps in a production environment, as it might open up some security vulnerabilities.

- Enable Wordpress Debug Mode in wp-config.php
    - You can do this by setting ```define('WP_DEBUG', false)``` to ```define('WP_DEBUG', true)```
- Enable Wordpress Debug Logging in wp-config.php
    - You can do this by adding  ```define('WP_DEBUG_LOG', true)```
    
If you are encountering errors, the debug.log file (located in wp-content/) is the place to search for an error description.
When tailing this log you will be able to see the errors flow in at the exact point they are triggered.

