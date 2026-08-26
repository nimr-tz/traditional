# Building the check-in app

The app is Expo managed — there is no `android/` directory and no APK in the
repo. Builds run on EAS and produce a file you sideload onto staff phones; this
app is not going through the Play Store.

## One-time setup

```sh
cd checkin-app
npx eas-cli login          # the NIMR Expo account
npx eas-cli init           # writes expo.extra.eas.projectId into app.json — commit it
```

`eas init` is what links this directory to a project on your Expo account. It is
the one value that cannot be committed ahead of time, because it is issued by
Expo when the project is created.

There is no global `eas` to install: every command here, and both `build:*`
scripts in `package.json`, go through `npx eas-cli`, which fetches the pinned
CLI on first use. Logging in is still one-off — the session is stored under
`~/.expo`, not in this directory.

## Building an APK for the venue

```sh
npm run build:staff
```

That runs the `staff` profile in `eas.json`: an Android **APK** (not an app
bundle, which phones cannot install directly), internal distribution, with the
build number auto-incrementing on EAS. When it finishes, EAS prints a download
URL — open it on each staff phone, or `adb install` it over USB.

Android blocks installs from outside the Play Store until the phone is told to
allow it: **Settings → Apps → Special access → Install unknown apps**, for
whichever browser downloaded the file.

## Which profile to use

| Profile | Output | For |
| --- | --- | --- |
| `development` | APK with the dev client | Debugging against a local server |
| `preview` | APK | Trying a change without bumping the build number |
| `staff` | APK, auto-incrementing | **The build you hand to venue staff** |
| `production` | AAB | Only if this ever goes to the Play Store |

## The API address is baked in at build time

`src/config/index.ts` reads `EXPO_PUBLIC_API_BASE_URL`, and `eas.json` sets it
to `https://tmsc.apps.nimr.or.tz/api` for every profile that produces a
shippable build. Without it the app falls back to `10.0.2.2:8000` — the Android
emulator's alias for a developer's laptop — which is useless at a venue.

Two things follow from that:

- **Rebuild after changing the URL.** It is compiled in, not read at runtime.
- **It must be HTTPS.** Android 9 and later block cleartext HTTP by default, so
  a plain `http://` address will fail on a real phone even when it works in the
  emulator.

## Before handing out a build

- Staff accounts exist and can sign in (`role:staff`, or `finance` for anyone
  who needs to settle payments at the desk).
- The venue has reachable network to `tmsc.apps.nimr.or.tz`. Every action is a
  live API call — there is no offline queue.
- Camera permission is requested on first scan; the prompt text lives in
  `app.json`.
