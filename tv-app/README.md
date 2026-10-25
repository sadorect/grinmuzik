# GrinMuzik TV app

The big-screen GrinMuzik interface: rows of covers you move through with a TV
remote, a full-screen player, search with an on-screen keyboard, and optional
sign-in by code (approved at grinmuzik.com/tv on a phone). It talks to the
`/api/tv` endpoints in the Laravel app.

It is a small Preact app built for the oldest TV we support, a 2017 Samsung
(Tizen 3.0, about Chrome 47). The build lowers everything to ES5, and
`scripts/check-legacy.mjs` fails the build if anything newer, or CSS that TV
cannot read (grid, custom properties, flex `gap`), slips through.

## Build

```sh
npm ci
npm run build
```

This writes:

- `build/tizen/`: the app plus `config.xml` and `icon.png`, ready to sign as a Samsung TV app
- `../public/tv-app/`: the same app on the website at `/tv-app/`, for trying in a desktop browser (arrow keys, Enter, Esc for Back)

## Try it without the backend

```sh
npm run build
node scripts/mock-server.mjs 8130 path/to/any.wav
```

Open http://127.0.0.1:8130/. The mock serves fake rows, generated covers and
the given audio file for every song, and approves sign-in on its second poll.
`GET /mock/log` lists the plays the app reported.

## Install on a Samsung TV (Developer Mode)

You need a computer on the same network as the TV.

1. Install [Tizen Studio](https://developer.tizen.org/development/tizen-studio/download).
   In its Package Manager, add **TV Extensions** and the **Samsung Certificate Extension**.
2. On the TV, open **Apps**, then press **1 2 3 4 5** on the remote. Turn
   **Developer mode** on, enter the computer's IP address, and restart the TV.
3. In Tizen Studio, open **Device Manager** → **Remote Device Manager**, add the
   TV's IP address and connect.
4. Open **Certificate Manager**, create a **Samsung** certificate profile for
   **TV**, and sign in with a Samsung account. The connected TV is added to the
   certificate automatically.
5. Package and install (the profile name is the one from step 4):

   ```sh
   tizen package -t wgt -s <profile> -- build/tizen
   tizen install -n build/tizen/GrinMuzik.wgt -t <tv-name-from-device-manager>
   ```

The app calls https://grinmuzik.com, so the `/api/tv` endpoints must be live first.

## Remote keys

| Key | Does |
| --- | --- |
| Arrows / OK | Move and select |
| Back | Previous screen; on Home, asks before exiting |
| Play, Pause, Play/Pause, Stop | Control playback from any screen |
| Rewind / Fast-forward | Jump 10 seconds |
| Previous / Next track | Skip |
