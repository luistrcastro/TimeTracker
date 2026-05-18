<script setup lang="ts">
</script>
<template>
  <v-app theme="light">
    <v-main>
      <div class="auth-bg h-100">
        <v-container class="fill-height" fluid>
          <v-row align="center" justify="center">
            <v-col cols="12" sm="8" md="5" lg="4">
              <slot />
            </v-col>
          </v-row>
        </v-container>
      </div>
    </v-main>
  </v-app>
</template>

<style lang="scss">
@import url(https://fonts.googleapis.com/css?family=Exo:100);

$bg-url: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAIAAACRXR/mAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAIGNIUk0AAHolAACAgwAA+f8AAIDpAAB1MAAA6mAAADqYAAAXb5JfxUYAAABnSURBVHja7M5RDYAwDEXRDgmvEocnlrQS2SwUFST9uEfBGWs9c97nbGtDcquqiKhOImLs/UpuzVzWEi1atGjRokWLFi1atGjRokWLFi1atGjRokWLFi1af7Ukz8xWp8z8AAAA//8DAJ4LoEAAlL1nAAAAAElFTkSuQmCC";
$bg-width: 50px;
$bg-height: 50px;

@keyframes bg-scrolling-reverse {
  100% { background-position: $bg-width $bg-height; }
}

.auth-bg {
  position: relative;

  &::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url($bg-url) repeat 0 0;
    opacity: 0.6;
    animation: bg-scrolling-reverse 3s linear infinite;
  }

  > * {
    position: relative;
    z-index: 1;
  }
}

.auth-brand {
  &__icon {
    display: block;
    margin: 0 auto;
    filter: drop-shadow(0 2px 8px rgba(91, 106, 245, 0.35));
  }

  &__name {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #1a1a2e;
    line-height: 1.1;
  }

  &__tagline {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 4px;
    letter-spacing: 0.02em;
  }
}
</style>
