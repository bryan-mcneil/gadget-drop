===POST===
AUTHOR: Bryan McNeil
TITLE: Bluetooth Keeps Disconnecting on Windows 11? Fix It
EXCERPT: Bluetooth keeps disconnecting on Windows 11? One power setting stops most dropouts. Here are the exact steps, plus fallbacks when a driver is to blame.
TYPE: tech_tip
CATEGORY: Audio & Home Theater
TAGS: Bluetooth | Windows 11 | troubleshooting | wireless audio
SOURCE_URL: https://support.microsoft.com/en-us/windows/bluetooth-keeps-disconnecting-in-windows-81a9cb1e-d15c-4e72-b025-cc0d992dca9f
SEO_SCORE: 91
META_TITLE: Bluetooth Keeps Disconnecting on Windows 11? How to Fix Dropouts
META_DESCRIPTION: Bluetooth keeps disconnecting on Windows 11? Turn off one power setting to stop the dropouts, then work through driver and service fixes that last.
FOCUS_KEYWORD: Bluetooth keeps disconnecting Windows 11
TARGET_QUERY: bluetooth keeps disconnecting windows 11
SLUG: bluetooth-keeps-disconnecting-windows-11-fix
BODY:
Your earbuds cut out mid-song. The wireless mouse freezes for a second, then snaps back. Your speaker drops the call and reconnects on its own. If Bluetooth keeps disconnecting on Windows 11, the cause is almost never the device you paired. It's usually a single power-saving setting Windows flips on by default, and you can turn it off in under a minute.

---

## Turn Off Bluetooth Power Management in Windows 11

Windows is allowed to shut down your Bluetooth radio to save battery, and when it does, everything paired to it drops. Here's how to stop that:

1. Right-click the **Start** button and choose **Device Manager**.
2. Scroll down and click the arrow next to **Bluetooth** to expand it.
3. Find your Bluetooth adapter. It's the entry with a name like "Intel Wireless Bluetooth" or "Realtek Bluetooth Adapter," not your individual earbuds or mouse.
4. Right-click that adapter and select **Properties**.
5. Open the **Power Management** tab.
6. Uncheck **Allow the computer to turn off this device to save power**.
7. Click **OK**, then restart your PC.

That's the fix that resolves the problem for most people. If your adapter has no Power Management tab, you likely picked the paired device instead of the radio itself, so go back and select the adapter.

---

## Why This Works

Laptops aggressively power down idle hardware to stretch battery life. Bluetooth counts as idle in the gaps between audio packets, so Windows briefly cuts power to the radio, and your headphones read that as a lost connection. Unchecking the setting tells Windows to leave the radio on full-time. You trade a tiny amount of battery for a link that stays up. This fix comes up again and again in r/techsupport threads and on Microsoft's own support pages for a reason: the power setting is the single most common culprit.

---

## If That Didn't Work

Try these in order, cheapest effort first.

1. **Run the built-in troubleshooter.** Open the **Get Help** app from the Start menu, search "Bluetooth," and let the automated Bluetooth troubleshooter run its diagnostics. It catches stopped services and simple misconfigurations on its own.
2. **Confirm the Bluetooth Support Service is running.** Press **Windows + R**, type `services.msc`, and press Enter. Find **Bluetooth Support Service**, right-click it, choose **Properties**, set **Startup type** to **Automatic**, and click **Start** if it's stopped. If that service isn't running, connections drop constantly.
3. **Update the driver from your laptop maker, not just Windows Update.** Go to the support page for your exact laptop model (Dell, HP, Lenovo, and so on) and download the latest Bluetooth or Wi-Fi/Bluetooth combo driver. Intel and Realtek push stability fixes for short dropouts that Windows Update is often months behind on.
4. **Remove and re-pair the device.** Go to **Settings > Bluetooth & devices**, click the three dots next to the problem device, choose **Remove device**, then pair it again fresh. A corrupted pairing profile can cause repeat drops that no setting will fix.

---

## Pro Tips to Keep It From Coming Back

- **Keep your radio away from USB 3.0 ports and hubs.** USB 3.0 throws off interference right in the 2.4GHz band Bluetooth uses. If you run a USB Bluetooth dongle, plug it into a front port or a short extension cable, well clear of an external drive or USB hub.
- **Check for driver updates every couple of months.** Wireless drivers are one of the few components where a fresh version genuinely fixes dropouts. Set a reminder rather than waiting for the next problem.
- **Rule out the earbuds before blaming the PC.** If a set only stutters on your computer but holds a rock-solid link to your phone, the fix is on the Windows side. Earbuds with a strong, stable connection like the ones in [our Soundcore Liberty 5 Pro review](/posts/soundcore-liberty-5-pro-review-worth-the-upgrade) make that test easy, since a drop then points squarely at the laptop's radio or driver.

Work through these in order and the disconnects almost always stop at step one. When they don't, it's a driver or service issue, and the fallbacks above cover the rest.
