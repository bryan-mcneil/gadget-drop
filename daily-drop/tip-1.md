===POST===
AUTHOR: Bryan McNeil
TITLE: DJI Drone Won't Connect to Phone? Fix the Camera Feed
EXCERPT: DJI drone won't connect or stuck on a black camera feed? Here's the exact restart order and cable fix that clears it, step by step.
TYPE: tech_tip
CATEGORY: Cameras
TAGS: dji | drones | troubleshooting | dji-fly-app
SOURCE_URL: https://support.dji.com/help/content?customId=en-us03400006810&spaceId=34&re=US&lang=en
SEO_SCORE: 90
META_TITLE: DJI Drone Won't Connect? How to Fix the Camera Feed (2026)
META_DESCRIPTION: DJI drone won't connect or stuck on a black camera feed? Follow this step-by-step restart, cable, and re-bind fix to get your video back.
FOCUS_KEYWORD: DJI drone won't connect
TARGET_QUERY: dji drone won't connect to phone
SLUG: dji-drone-wont-connect-fix
BODY:
You power up the drone, open DJI Fly, and nothing lines up. Either the app sits on "Aircraft Disconnected," or the flight data shows up fine but the camera feed is a black rectangle. Before you assume the camera is broken, run this sequence. The fix is almost always software or a tired cable, not hardware.

## How to Fix a DJI Drone That Won't Connect

Work through these in order. Most people are flying again by the fourth step.

1. **Force-close DJI Fly, don't just minimize it.** Swipe the app fully off your recent-apps list. A backgrounded app holds a stale connection that blocks a clean handshake.
2. **Check both battery levels.** The drone and the controller each need at least two of their LEDs lit. Controller batteries drain faster than you'd expect, and a low controller is a common silent culprit.
3. **Restart in the right order.** Power off the drone, then the controller. Turn the controller on first, wait for it to settle, then power on the drone. Reopen DJI Fly last.
4. **Do the cable flip.** If you fly with a phone cable, unplug the USB-C or Lightning connector, turn it 180 degrees, and plug it back in. Cables wear on one side of the pins and can carry charge while failing to pass the high-speed video data.
5. **Switch the transmission channel to manual.** In DJI Fly, tap the three-dot menu, open Transmission, set Channel Mode to Manual, and try the other frequency band. Crowded 2.4GHz airspace in a neighborhood will choke the video stream on auto.
6. **Re-bind the controller and drone.** If it still won't link, re-pair them from the drone's settings. The digital handshake between the two can get corrupted, and a fresh bind rebuilds it.

## Why This Works

DJI Fly reports two very different failures with similar symptoms. "Aircraft Disconnected" means the radio link between controller and drone is dead, which is a pairing or battery problem. "No Image Transmission" means the drone is flying fine but the video pipe is blocked, which points at the cable, the frequency band, or a firmware mismatch. Running the restart in order rebuilds the radio link, and the cable flip and channel switch clear the two most common video blockages. On a controller-free drone like a Neo that you fly straight from your phone, the same logic applies: force-close the app, check the band, and confirm your phone and the drone are on synced firmware.

## If That Didn't Work

Try these next, cheapest and easiest first.

- **Remove your phone case.** A thick or ill-fitting case stops the cable from seating fully in the port. DJI's own support notes call out poor contact from phone cases as a frequent cause. Pop the case off and reconnect.
- **Sync the firmware.** A black screen right after an update usually means the aircraft, controller, and camera are on mismatched firmware. Connect the drone to a computer with DJI Assistant 2 (Consumer series) and update every component so they match. Only start an update with all batteries above 50 percent, because a power loss mid-update can brick the drone.
- **Reset the camera settings.** In the camera view, open the settings menu and choose Reset Camera Settings or Restore Defaults. A bad setting can blank the preview without touching the hardware.
- **Factory reset the aircraft as a last resort.** If nothing else clears it, reset the drone to defaults, then re-bind and re-calibrate before your next flight.

## Pro Tips to Keep It From Happening Again

- **Update firmware on a schedule, not mid-trip.** Check for updates at home before you head out, so you never hit a mismatch in the field where you can't fix it.
- **Keep a spare certified cable in the bag.** The cable is the single most failure-prone part of the connection chain, and a $12 backup saves a wasted afternoon.
- **Charge the controller separately.** Since it dies before the drone, top it off the night before rather than assuming a shared charge lasts.

If your connection headaches are really a sign it's time for a newer, simpler drone, our [DJI Neo 2 review](/posts/dji-neo-2-review) covers a palm-launched model that skips the controller entirely and flies straight from your phone.
