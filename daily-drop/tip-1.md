===POST===
AUTHOR: Bryan McNeil
TITLE: Fix Bluetooth That Keeps Disconnecting on Windows 11
EXCERPT: Bluetooth keeps disconnecting on Windows 11? The real fix is one hidden power setting. Here are the exact steps, plus fallbacks for when it fails.
TYPE: tech_tip
CATEGORY: Computers & Accessories
TAGS: bluetooth | windows 11 | troubleshooting | drivers
SOURCE_URL: https://support.microsoft.com/en-us/windows/hardware/bluetooth/bluetooth-keeps-disconnecting-in-windows
SEO_SCORE: 90
META_TITLE: Fix Bluetooth Keeps Disconnecting on Windows 11 (Real Fix)
META_DESCRIPTION: Bluetooth keeps disconnecting on Windows 11? Turn off one hidden power setting, plus driver and pairing fallbacks. Exact step-by-step menu paths.
FOCUS_KEYWORD: bluetooth keeps disconnecting windows 11
TARGET_QUERY: bluetooth keeps disconnecting windows 11
SLUG: fix-bluetooth-disconnecting-windows-11
BODY:
Your mouse freezes for a second. Your earbuds cut out mid-song. Your keyboard drops a keystroke, then reconnects like nothing happened. If your Bluetooth keeps disconnecting on Windows 11, the cause is almost never the device itself. It's a power-saving setting buried in Windows that quietly shuts off your Bluetooth radio to save a few minutes of battery. Here's how to turn it off and make your connection stick.

## Turn Off the Power Setting That Kills Your Bluetooth

This is the fix that solves the problem for most people, and it takes about a minute.

1. Press the **Windows key**, type **Device Manager**, and open it.
2. In the list, find **Bluetooth** and click the arrow to expand it.
3. Right-click your Bluetooth **adapter**, not your headphones or mouse. It's usually named something like "Intel Wireless Bluetooth" or "Realtek Bluetooth Adapter." Choose **Properties**.
4. Open the **Power Management** tab at the top.
5. Uncheck the box that says **"Allow the computer to turn off this device to save power."**
6. Click **OK**, then restart your PC if it asks.

That single checkbox is the difference between a rock-solid connection and one that drops every time your laptop idles for a moment.

## Why This Works

Windows treats the Bluetooth radio like any other component it can power down to stretch battery life. On laptops especially, it's set to switch the radio off during short periods of inactivity. The trouble is that "inactivity" includes the split second between you moving your mouse and clicking, so Windows cuts power, your device disconnects, and it has to scramble to reconnect. You experience that as a stutter or a drop. Unchecking the power setting tells Windows to leave the radio alone, which is what you want on a desktop always plugged in, and usually worth the tiny battery cost on a laptop too.

## If That Didn't Work

Work through these in order, cheapest and fastest first.

1. **Run the built-in troubleshooter.** Go to **Settings > System > Troubleshoot > Other troubleshooters**, find **Bluetooth**, and click **Run**. It catches common misconfigurations automatically and is a quick first pass.
2. **Turn off Energy Saver.** Open **Settings > System > Power & battery** and switch **Energy Saver** off. Like the power-management checkbox, aggressive energy saving can throttle the Bluetooth radio.
3. **Update or reinstall the driver.** Back in **Device Manager**, right-click your Bluetooth adapter and choose **Update driver > Search automatically**. If that finds nothing and the problem persists, right-click the adapter again, choose **Uninstall device**, then restart. Windows reinstalls a clean driver on boot, which clears up corrupted-driver cases that come up constantly in r/techsupport threads.
4. **Remove and re-pair the device.** Go to **Settings > Bluetooth & devices**, click the three dots next to the misbehaving device, choose **Remove device**, then pair it again from scratch. A stale pairing profile can cause repeat drops.
5. **Disable Fast Startup.** Open **Control Panel > Hardware and Sound > Power Options > Choose what the power buttons do**, click **Change settings that are currently unavailable**, and uncheck **Turn on fast startup**. Fast Startup can leave drivers in a half-loaded state after shutdown, and Bluetooth is a frequent casualty.

## Pro Tips to Keep It From Coming Back

- **Keep your drivers current.** Check **Windows Update** monthly, or grab the latest Bluetooth driver straight from your laptop maker's support page. Outdated drivers are the single most common repeat offender.
- **Watch for 2.4GHz interference.** Bluetooth shares the crowded 2.4GHz band with Wi-Fi, USB 3.0 hubs, and microwaves. If drops happen near a specific spot, move the USB dongle or the PC a few inches, or plug a USB Bluetooth adapter into a front port instead of behind a metal case.
- **Better peripherals hold on better.** Cheap Bluetooth gear tends to drop first. A well-engineered device with a stable connection, like the one in our [Logitech MX Master 3S review](/posts/logitech-mx-master-3s-why-worth-it), reconnects fast and rarely stutters, which saves you from chasing settings in the first place.

If you've worked through all of this and one specific device still drops while everything else is fine, the problem is that device's battery or radio, not Windows. Everything else on your PC staying connected is the tell.
