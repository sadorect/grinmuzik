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
    && sdkmanager "platforms;android-${ANDROID_TARGET_SDK}" "build-tools;${ANDROID_TARGET_SDK}.0.0" "platform-tools" >/dev/null

RUN npm install -g @bubblewrap/cli

WORKDIR /work
DOCKERFILE
}

run_in_container() {
    docker run --rm -it \
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
        echo "==> Building the release bundle..."
        run_in_container "bubblewrap build --skipPwaValidation"
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
