=== Access Code Feeder ===
Contributors: trof
Donate link: https://www.donationalerts.com/r/svinuga
Tags: access code, bot, chatbot,satoshi,bonds
Requires at least: 2.0
Tested up to: 5.5
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html 



== Description ==
Access Code Feeder supplies other software (such as chat bot, access code store, etc.) with unique access codes from  pre-loaded list.

**Demos**<br>
* <a href="https://www.twitch.tv/satoshibank/videos" target="_blank">StreamElements Bot in Twitch</a> - Mention "Satoshi Bond" in chat, 
and the ChatBot will fetch Access Code (Satoshi Bonds are access codes too) and pass it back via <br><code> ${urlfetch http://not.going.to/tell/you/secret_feeder_url/} </code><br>
* <a href="https://wmexp.com/access-code-feeder-manager/" target="_blank">Feeder Manager</a> - Similar to admin interface.
* <a href="https://www.youtube.com/watch?v=v0WAz8OyY28&list=PLRv0B44q8TR8bWrEwtMd6e17oW8wdRVIv&index=4" target="_blank">Satoshi Bonds, Access Code Feeder and Chat Bot video</a>


**Features**<br>
* Supports all chat bots capable of URL Fetching (urlfetch(), fetch(), readapi(), customapi(), etc.). <br>
* Notifications on low contents. <br>
* Easy, intuitive management. <br>
* Works fine <a href="https://wmexp.com/satoshi-bonds-manager/" target="_blank">Satoshi Bonds</a> (<a href="https://www.youtube.com/watch?v=v0WAz8OyY28&list=PLRv0B44q8TR8bWrEwtMd6e17oW8wdRVIv&index=4" target="_blank">HowTo video</a>) <br>


== Installation ==
1. The easiest way is to login to you WordPress dashboard, go to Plugins >> Add New, search for Access Code Feeder, and click to install. 
You can also download the zip file from this page and upload it from the Plugins >> Add New > Upload page.
1. Activate the plugin through the WordPress 'Plugins' menu

= Configuration =
1. Press "Add Feeder" button, enter the name of your new feeder.
1. Press "Access Codes" button, paste list of your access codes into appropriate field, hit "Save".
1. Press "Usage" button, copy the URL of the feeder, use it in your chat bot or other software.


== Frequently asked questions ==

= What is it for ? =

If you have bunch of access codes, and want to use them as prized in your streaming chat, or sell them one at a time, this plugin will simplify the process.

= What does it work ? =

Well, it is really simple, in the plugin you create new Feeder, and load it with list of access codes, each code in a new line. 
Feeder provides a secret URL you mention in the software you have control of, for example a chat bot. 
When the chat bot decides it is time for a prize, it pools the URL, and the Feeder responds with next access code, than access code is removed from the list.
Chat bot throws the acquired access code to the chat. That's it. 

= Why the URL is secret ? =

When someone knows the Feeder URL, they can pull your access codes one-by-one. So, use the URL only in server-side software, not in the JavaScript on the client side, where people can read it. 
If URL is compromised somehow, just create another Feeder and move your Access codes there. The URLs are virtually impossible to guess or find using brute force, so they are secure enough.

= Where it get the  Access Codes ? =
There is plenty of give-aways over the Internet.  You can also generate your own <a href="https://wmexp.com/satoshi-bonds-manager/" target="_blank">Satoshi Bonds</a> (<a href="https://www.youtube.com/watch?v=v0WAz8OyY28&list=PLRv0B44q8TR8bWrEwtMd6e17oW8wdRVIv&index=4" target="_blank">watch the video</a>).

== Screenshots ==

1. **Feeders List** - 
2. **Add new Feeder** - Entering Feeder Name.
3. **Load Access Codes** - No access codes  yet.
4. **Access Codes Loaded** - Just for Testing.
5. **Usage** - Usage information.

== Translations ==
* English - included
* Russian - included

== Changelog ==

= V1.0.3 - 06.07.2020 =
Minor errors fixed (emailing)<br>

= V1.0.2 - 28.06.2020 =
Minor errors fixed.<br>


= V1.0.1 - 10.04.2020 =
Minor functionality fixes.<br>
Language fixes.<br>
The <a href="https://www.youtube.com/watch?v=v0WAz8OyY28&list=PLRv0B44q8TR8bWrEwtMd6e17oW8wdRVIv&index=4" target="_blank">HowTo video</a> added.<br>

= V1.0.0 - 12.03.2020 =
Initial public release<br>


== Upgrade notice ==
Compatible with previous versions, no actions required.<br>