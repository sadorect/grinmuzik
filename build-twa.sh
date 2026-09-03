#!/usr/bin/env bash
#
# Build the GrinMuzik Android package (TWA) with Bubblewrap, in Docker.
#
# Docker because Bubblewrap needs a JDK and the Android SDK, and neither belongs
# on this web server. The container is disposable; only the keystore and the
# build output are kept.
#
# Usage:
#   ./build-twa.sh init     # first run: generate the Android project + keystore
#   ./build-twa.sh build    # produce app-release-bundle.aab for Play Console
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TWA_DIR="${APP_DIR}/build/twa"
IMAGE="grinmuzik/bubblewrap:latest"

# API 36 = Android 16. Play requires new apps to target API 36 from 31 Aug 2026.
# Bubblewrap's own templates still default lower, which is why twa-manifest.json
# pins compileSdkVersion/targetSdkVersion explicitly — do not drop those.
ANDROID_TARGET_SDK=36

# Bubblewrap hard-codes the build-tools version it shells out to (zipalign,
# apksigner) in BUILD_TOOLS_VERSION. If the image does not already have that
# exact version it tries to sdkmanager-install it at build time, which fails
# because the SDK is root-owned and the container runs as the calling user.
# Keep this pinned to the constant in:
#   @bubblewrap/core/dist/lib/androidSdk/AndroidSdkTools.js
BUBBLEWRAP_BUILD_TOOLS=36.1.0

# The Android Gradle Plugin that Bubblewrap scaffolds into the project (see
# 'com.android.tools.build:gradle' in build/twa/build.gradle) pins its OWN
# build-tools version, independent of the two above. AGP 8.9.x wants 35.0.0.
# Check the generated build.gradle if a future Bubblewrap bumps AGP.
AGP_BUILD_TOOLS=35.0.0

mkdir -p "${TWA_DIR}"

build_image() {
    if docker image inspect "${IMAGE}" >/dev/null 2>&1; then
        return
    fi

    echo "==> Building Bubblewrap image (first run only, this takes a while)..."

    docker build -t "${IMAGE}" - <<DOCKERFILE
FROM eclipse-temurin:17-jdk

ENV ANDROID_HOME=/opt/android-sdk
ENV PATH="\${PATH}:\${ANDROID_HOME}/cmdline-tools/latest/bin:\${ANDROID_HOME}/platform-tools"

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl unzip git ca-certificates \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p \${ANDROID_HOME}/cmdline-tools \
    && curl -fsSL -o /tmp/tools.zip https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip \
    && unzip -q /tmp/tools.zip -d \${ANDROID_HOME}/cmdline-tools \
    && mv \${ANDROID_HOME}/cmdline-tools/cmdline-tools \${ANDROID_HOME}/cmdline-tools/latest \
    && rm /tmp/tools.zip

RUN yes | sdkmanager --licenses >/dev/null \
    && sdkmanager "platforms;android-${ANDROID_TARGET_SDK}" \
                  "build-tools;${ANDROID_TARGET_SDK}.0.0" \
                  "build-tools;${BUBBLEWRAP_BUILD_TOOLS}" \
                  "build-tools;${AGP_BUILD_TOOLS}" \
                  "platform-tools" >/dev/null

# The container runs as the calling (non-root) user so build output is not
# root-owned on the bind mount. That leaves the root-owned SDK unwritable, and
# Gradle aborts with "The SDK directory is not writable" the moment it wants a
# component we did not pre-install. Making the SDK world-writable is safe here:
# the container is disposable and holds no secrets.
RUN chmod -R a+w \${ANDROID_HOME}

# Bubblewrap's AndroidSdkTools.validatePath() only accepts an SDK root holding
# a 'tools' or 'bin' directory; modern command-line tools install to
# cmdline-tools/latest, so validation fails with "The provided androidSdk isn't
# correct." Symlink 'tools' (NOT 'bin') to the whole cmdline-tools/latest —
# sdkmanager derives its own lib/ path relative to the script, so exposing only
# bin/ makes it fail with ClassNotFoundException: SdkManagerCli.
RUN ln -s \${ANDROID_HOME}/cmdline-tools/latest \${ANDROID_HOME}/tools

RUN npm install -g @bubblewrap/cli

WORKDIR /work
DOCKERFILE
}

run_in_container() {
    # Only allocate a TTY when there actually is one, so this can run from CI or
    # a non-interactive shell instead of dying with "cannot attach stdin to a
    # TTY-enabled container". Note that `bubblewrap build` IS interactive when
    # twa-manifest.json has changed since the project was generated -- which is
    # every version bump -- so the build path runs `update` first to leave
    # nothing for it to ask about.
    local tty_flags=()
    if [ -t 0 ] && [ -t 1 ]; then
        tty_flags=(-it)
    fi

    # Pass the signing passwords through when they are set in the environment,
    # so the build can run unattended. Bubblewrap reads exactly these names;
    # when unset it falls back to prompting, which needs a TTY.
    local pass_flags=()
    if [ -n "${BUBBLEWRAP_KEYSTORE_PASSWORD:-}" ]; then
        pass_flags+=(-e BUBBLEWRAP_KEYSTORE_PASSWORD)
    fi
    if [ -n "${BUBBLEWRAP_KEY_PASSWORD:-}" ]; then
        pass_flags+=(-e BUBBLEWRAP_KEY_PASSWORD)
    fi

    docker run --rm "${tty_flags[@]}" "${pass_flags[@]}" \
        --user "$(id -u):$(id -g)" \
        -v "${TWA_DIR}:/work" \
        -v "${APP_DIR}/twa-manifest.json:/work/twa-manifest.json" \
        -e HOME=/work \
        "${IMAGE}" \
        bash -lc "$1"
}

case "${1:-}" in
    init)
        build_image
        echo "==> Initialising the Android project from the live web manifest..."
        # Bubblewrap prompts for keystore details on first run. The keystore it
        # creates is the UPLOAD key — Play re-signs with its own app signing key,
        # and it is that key's SHA-256 that must go into PLAY_APP_FINGERPRINT.
        run_in_container "bubblewrap init --manifest https://grinmuzik.com/manifest.webmanifest"
        echo
        echo "Keystore written to ${TWA_DIR}. BACK IT UP — losing it means you"
        echo "cannot ship an update to the same listing."
        ;;

    build)
        build_image

        # Fold twa-manifest.json into the generated Gradle project FIRST.
        # Skipping this is not merely untidy: `bubblewrap build` would stop to
        # ask "there are changes in twa-manifest.json, apply them?", get no
        # answer without a TTY, and carry on building the project as it stands
        # -- silently shipping the PREVIOUS appVersionCode, which Play then
        # rejects as a duplicate.
        echo "==> Applying twa-manifest.json to the Android project..."
        # DO NOT hand-edit appVersionCode in twa-manifest.json. `update` owns
        # that number: it increments whatever is there by one and writes it
        # back, so a manual bump on top of it skips a code (1 -> 3). Leave the
        # field holding the last SHIPPED code and let this step produce the
        # next one. Only appVersionName is yours to set, and it has to be
        # passed on the command line -- update ignores it in the manifest.
        # --skipVersionUpgrade is not an escape hatch here: it suppresses both.
        want_name="$(python3 -c "import json;print(json.load(open('${APP_DIR}/twa-manifest.json'))['appVersionName'])")"
        run_in_container "bubblewrap update --appVersionName '${want_name}'"

        echo "==> Building the release bundle..."
        run_in_container "bubblewrap build --skipPwaValidation"

        # Bubblewrap can exit 0 having produced nothing (e.g. it fell back to
        # prompting for the keystore password and got no input). Without this
        # check the script cheerfully reports a bundle that is not there.
        if [ ! -f "${TWA_DIR}/app-release-bundle.aab" ]; then
            echo "ERROR: build reported success but ${TWA_DIR}/app-release-bundle.aab does not exist." >&2
            echo "       If it stopped at a password prompt, export BUBBLEWRAP_KEYSTORE_PASSWORD" >&2
            echo "       and BUBBLEWRAP_KEY_PASSWORD before running this script." >&2
            exit 1
        fi

        # Existence is not freshness. A stale bundle left over from a previous
        # run satisfies the check above, so compare against the manifest that
        # was supposed to drive this build.
        if [ "${APP_DIR}/twa-manifest.json" -nt "${TWA_DIR}/app-release-bundle.aab" ]; then
            echo "ERROR: ${TWA_DIR}/app-release-bundle.aab is older than twa-manifest.json." >&2
            echo "       The build did not actually run; the bundle on disk is stale." >&2
            exit 1
        fi

        # Last word on what is really in the bundle. versionCode lives in the
        # generated Gradle file, so a mismatch here means `update` did not take.
        want_code="$(python3 -c "import json;print(json.load(open('${APP_DIR}/twa-manifest.json'))['appVersionCode'])")"
        have_code="$(sed -n 's/^[[:space:]]*versionCode[[:space:]]\+\([0-9]\+\).*/\1/p' "${TWA_DIR}/app/build.gradle" | head -1)"

        have_name="$(sed -n 's/^[[:space:]]*versionName[[:space:]]\+"\([^"]*\)".*/\1/p' "${TWA_DIR}/app/build.gradle" | head -1)"

        if [ "${want_code}" != "${have_code}" ] || [ "${want_name}" != "${have_name}" ]; then
            echo "ERROR: built version ${have_name} (${have_code}) does not match" >&2
            echo "       twa-manifest.json: ${want_name} (${want_code})." >&2
            exit 1
        fi

        echo "    version ${have_name} (code ${have_code}) confirmed in the generated project."

        echo
        echo "==> Bundle: ${TWA_DIR}/app-release-bundle.aab"
        echo "    Upload it in Play Console, then copy the SHA-256 from"
        echo "    Setup → App signing into PLAY_APP_FINGERPRINT and clear the"
        echo "    config cache so /.well-known/assetlinks.json starts serving."
        ;;

    *)
        echo "Usage: $0 {init|build}" >&2
        exit 1
        ;;
esac
