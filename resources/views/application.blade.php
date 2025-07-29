<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="{{ asset('favicon.ico') }}" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ config('app.name', 'Time Tracker') }}</title>
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  @vite(['resources/js/main.js'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      <div class="loading-logo">
        <!-- SVG Logo -->
        <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
            width="1.875em" height="1.5em" viewBox="0 0 554 556" preserveAspectRatio="xMidYMid meet">

        <g transform="translate(0.000000,556.000000) scale(0.100000,-0.100000)"
        fill="#8E56FF" stroke="none">
        <path d="M282 5473 c-63 -31 -63 -31 -60 -471 3 -383 4 -399 25 -456 44 -115
        8 -68 776 -1024 147 -184 279 -356 292 -383 74 -145 73 -324 -1 -471 -13 -25
        -74 -108 -136 -185 -375 -459 -775 -958 -834 -1040 -60 -84 -87 -139 -109
        -224 -23 -87 -22 -1046 1 -1090 31 -61 6 -59 891 -59 790 0 809 1 841 20 18
        11 37 32 42 46 6 15 10 175 10 384 l0 360 -29 32 -29 33 -262 0 -263 0 6 395
        c6 438 12 486 72 617 53 115 74 142 380 484 222 247 245 282 245 372 0 95 -3
        99 -412 584 -150 178 -196 247 -227 339 -33 99 -39 173 -43 514 l-3 325 258 5
        259 5 24 28 24 28 0 380 c0 320 -2 385 -15 409 -32 63 5 60 -882 60 -726 0
        -811 -2 -841 -17z"/>
        <path d="M3523 5470 c-17 -10 -37 -28 -42 -39 -7 -13 -11 -149 -11 -396 0
        -408 1 -419 55 -443 18 -8 101 -12 264 -12 131 0 240 -2 242 -4 2 -2 1 -159
        -2 -348 -7 -379 -12 -415 -74 -543 -42 -87 -88 -149 -340 -450 -256 -307 -275
        -337 -275 -429 0 -84 55 -159 360 -496 151 -166 211 -245 255 -335 73 -148 77
        -187 90 -787 l5 -238 -246 0 c-268 0 -300 -6 -323 -56 -7 -17 -11 -136 -11
        -380 0 -303 2 -359 16 -385 31 -61 7 -59 881 -59 774 0 799 1 830 20 66 40 65
        27 61 588 -4 457 -6 512 -22 562 -44 133 -61 156 -799 1054 -191 233 -257 320
        -290 385 -41 82 -42 88 -45 195 -6 187 32 270 235 517 389 472 783 959 820
        1012 23 34 55 98 70 142 l28 80 0 395 c0 379 -1 396 -20 422 -11 15 -33 32
        -49 38 -19 6 -300 10 -830 10 -775 0 -802 -1 -833 -20z"/>
        <path d="M2078 4200 l-30 -31 4 -172 c4 -139 9 -183 25 -227 45 -122 96 -199
        263 -395 217 -255 258 -365 248 -653 -8 -213 -52 -322 -223 -557 -309 -423
        -363 -507 -477 -741 -77 -157 -89 -202 -63 -227 15 -16 88 -17 920 -17 808 0
        905 2 919 16 21 21 15 43 -56 199 -102 224 -193 368 -445 708 -250 336 -277
        402 -278 672 0 148 3 179 22 240 34 108 99 211 235 372 184 218 226 284 262
        403 22 72 39 322 25 372 -18 70 6 68 -694 68 l-627 0 -30 -30z"/>
        </g>
</svg>
      </div>
      <div class="loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>
    </div>
  </div>
  
  <script>
    const loaderColor = localStorage.getItem('materio-initial-loader-bg') || '#FFFFFF'
    const primaryColor = localStorage.getItem('materio-initial-loader-color') || '#9155FD'

    if (loaderColor)
      document.documentElement.style.setProperty('--initial-loader-bg', loaderColor)

    if (primaryColor)
      document.documentElement.style.setProperty('--initial-loader-color', primaryColor)
  </script>
</body>

</html>
