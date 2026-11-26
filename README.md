# Bunny Stream video analytics for Moodle 4.5 (local_bunnyanalytics)

Tracks how students watch Bunny Stream videos embedded in Moodle courses (views, plays, pauses,
replays, rewinds, unique % watched, completion) and shows it in a report for admins and teachers.
A daily task also pulls titles, views and engagement scores from the Bunny Stream API.

## Install / update
1. Copy the `bunnyanalytics` folder to `<moodle>/local/bunnyanalytics` (replace the old one).
2. Visit Site administration → Notifications and confirm the upgrade.
3. Settings: Site administration → Plugins → Local plugins → Bunny Stream video analytics
   (Stream library ID + library API key from Bunny → Stream → your library → API).

## Reports
- Site: Site administration → Reports → Video analytics
- Course: course → More → Video analytics (click a video for each student)

## Troubleshooting
Every course page ends with an HTML comment `<!-- bunnyanalytics: ... -->` that says whether the
tracker loaded (`loaded course=.. cm=..`) or why not (`skipped ...` / `error ...`).

## Changes
- 0.1.2: pauses detected even when the player doesn't report them; one timeline drag counts as one seek/rewind.
- 0.1.1: footer status comment, tracking treated as on unless explicitly disabled,
  course detection fallback via page context.
- 0.1.0: first version.
