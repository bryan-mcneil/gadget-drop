===POST===
AUTHOR: Bryan McNeil
TITLE: Robot Vacuum Won't Connect to Wi-Fi? The 2.4GHz Fix
EXCERPT: If your robot vacuum won't connect to Wi-Fi, the culprit is almost always your 2.4GHz band. Here's the exact router fix that gets setup to finish.
TYPE: tech_tip
CATEGORY: Smart Home
TAGS: robot vacuum | wifi | 2.4ghz | smart home | troubleshooting
SOURCE_URL: https://www.techradar.com/home/robot-vacuums/what-to-do-if-your-robot-vacuum-keeps-losing-connection
SEO_SCORE: 87
META_TITLE: Robot Vacuum Won't Connect to Wi-Fi? The 2.4GHz Fix
META_DESCRIPTION: Robot vacuum won't connect to Wi-Fi? It's almost always a 2.4GHz band issue. Here's the exact router setting to change so setup finishes.
FOCUS_KEYWORD: robot vacuum won't connect to wifi
TARGET_QUERY: robot vacuum won't connect to wifi
SLUG: robot-vacuum-wont-connect-to-wifi
BODY:
You unbox a new robot vacuum, download the app, and it stalls forever on "connecting." The vacuum blinks, the app times out, and you start wondering if you got a lemon. You almost certainly didn't. The problem is the Wi-Fi band, and the fix takes about five minutes in your router settings.

## Fix a Robot Vacuum That Won't Connect to Wi-Fi

Nearly every robot vacuum, Roborock, eufy, Dreame, Shark, and the rest, connects only to the 2.4GHz Wi-Fi band, not the faster 5GHz band. Modern routers hide both bands behind a single network name and quietly shuffle your devices between them. That is great for your laptop and terrible for a vacuum that can only see one of them. Here is how to force the connection.

1. Open your router's admin page. Type `192.168.0.1` or `192.168.1.1` into a browser, or use your router brand's app (Google Home, Eero, Deco, Orbi, and so on).
2. Find the Wi-Fi or wireless settings. Look for a feature called "Smart Connect," "band steering," or "one Wi-Fi name."
3. Turn that feature off, or split your bands. Splitting means giving the 2.4GHz and 5GHz networks two different names, for example `MyHouse-24` and `MyHouse-5`.
4. On your phone, connect to the 2.4GHz network on purpose. The vacuum's app pairs by handing your phone's current Wi-Fi to the vacuum, so your phone has to be on 2.4GHz during setup.
5. Run the vacuum's setup again, standing within a few feet of your main router. Once it connects, you can switch your phone back to 5GHz for daily use. The vacuum stays put.

## Why This Works

When your router broadcasts both bands under one name, your phone grabs 5GHz because it's faster. During setup the app tries to pass that 5GHz network to the vacuum, and the vacuum, which physically cannot tune to 5GHz, never completes the handshake. Forcing your phone onto the 2.4GHz network removes the mismatch. The vacuum finally receives credentials for a band it can actually join, and setup finishes.

## If That Didn't Work

Try these in order, cheapest first:

- **Change your encryption to WPA2.** Some routers default the 2.4GHz band to WPA3 or a WPA3-only mode that older smart devices can't read. In your router's wireless security settings, set the 2.4GHz band to "WPA2-PSK" or "WPA2/WPA3 mixed" and try setup again. This one fixes a surprising number of stuck connections.
- **Move away from mesh satellites during setup.** Mesh systems like Eero, Deco, and Orbi steer devices between nodes automatically, which confuses a vacuum mid-handshake. Do the initial pairing right next to the main router or gateway, not a satellite unit in another room.
- **Simplify your Wi-Fi password temporarily.** A few special characters trip up smart-home devices. If nothing else works, set a simple password with only letters and numbers, connect the vacuum, then change the password back later. The vacuum keeps working after the change.

## Pro Tips

- **Keep a dedicated 2.4GHz network.** Once you split your bands, leave them split. A separate 2.4GHz name makes every future smart-home device, plugs, bulbs, cameras, doorbells, connect on the first try instead of the fifth.
- **The 2.4GHz rule is nearly universal for smart home gear.** It caught people setting up the camera in our [eufy Indoor Cam E30 review](/posts/eufy-indoor-cam-e30-review-4k-without-the-monthly-fee) too. When any new device stalls at "connecting," check the band before you assume the hardware is broken.
- **Note your working setup.** After the vacuum connects, write down which network name and security mode worked. If you ever change routers or reset the vacuum, you'll skip the whole troubleshooting loop next time.

This band mismatch is the single most common reason a robot vacuum, or any budget smart-home device, refuses to finish setup. It shows up constantly in r/techsupport and manufacturer support forums, and it's almost never a defective unit. Change the band, and the "connecting" spinner finally stops.
