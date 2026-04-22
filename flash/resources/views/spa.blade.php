@php
  $assetManifestPath = public_path('build/asset-manifest.json');
  $assetManifest = file_exists($assetManifestPath)
    ? json_decode(file_get_contents($assetManifestPath), true)
    : [];
  $entry = $assetManifest['index.html'] ?? $assetManifest['src/main.ts'] ?? null;
  $frontendBuildReady = is_array($entry) && ! empty($entry['file']);

  $modulePreloads = [];
  $stylesheets = [];
  $collectChunkAssets = null;

  $collectChunkAssets = function ($chunkName) use (&$collectChunkAssets, $assetManifest, &$modulePreloads, &$stylesheets) {
    $chunk = $assetManifest[$chunkName] ?? null;

    if (! is_array($chunk)) {
      return;
    }

    if (! empty($chunk['file']) && empty($chunk['isEntry'])) {
      $modulePreloads[$chunk['file']] = true;
    }

    foreach ($chunk['css'] ?? [] as $cssFile) {
      $stylesheets[$cssFile] = true;
    }

    foreach ($chunk['imports'] ?? [] as $importedChunk) {
      $collectChunkAssets($importedChunk);
    }
  };

  foreach ($entry['css'] ?? [] as $cssFile) {
    $stylesheets[$cssFile] = true;
  }

  foreach ($entry['imports'] ?? [] as $importedChunk) {
    $collectChunkAssets($importedChunk);
  }

  $serviceWorkerVersion = file_exists(public_path('sw.js'))
    ? filemtime(public_path('sw.js'))
    : time();
  $webManifestVersion = file_exists(public_path('manifest.json'))
    ? filemtime(public_path('manifest.json'))
    : time();
  $serviceWorkerUrl = url('/sw.js').'?v='.$serviceWorkerVersion;
  $deployHelp = 'Frontend build is missing or incomplete. Upload flash/public/build/asset-manifest.json and flash/resources/views/spa.blade.php, or rerun upload-to-server.ps1/deploy.sh.';
@endphp

<!DOCTYPE html>
<html lang="en" class="dark">
  <head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/newlogo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#09090b">

    <!-- Base SEO -->
    <meta name="description" content="Klek AI - AI-powered platform for generating stunning images, creative designs, and visual content. Transform your ideas into art with cutting-edge artificial intelligence.">
    <meta name="keywords" content="AI image generation, artificial intelligence, creative design, AI art, image creator, AI design tool, توليد الصور بالذكاء الاصطناعي, تصميم إبداعي, ذكاء اصطناعي">
    <meta name="author" content="Klek AI">
    <link rel="canonical" href="https://klek.studio">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Klek AI">
    <meta property="og:title" content="Klek AI - AI Image Generation & Creative Design">
    <meta property="og:description" content="AI-powered platform for generating stunning images and creative designs. Transform your ideas into art.">
    <meta property="og:url" content="https://klek.studio">
    <meta property="og:image" content="https://klek.studio/icons/icon-512x512.png">
    <meta property="og:locale" content="en_US">
    <meta property="og:locale:alternate" content="ar_SA">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Klek AI - AI Image Generation & Creative Design">
    <meta name="twitter:description" content="AI-powered platform for generating stunning images and creative designs. Transform your ideas into art.">
    <meta name="twitter:image" content="https://klek.studio/icons/icon-512x512.png">

    <!-- PWA -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Klek AI">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <link rel="manifest" href="{{ asset('manifest.json') }}?v={{ $webManifestVersion }}">

    <title>{{ $frontendBuildReady ? 'Klek AI - AI Image Generation & Creative Design' : 'Klek AI - Frontend Build Missing' }}</title>
    @if ($frontendBuildReady)
      @foreach (array_keys($modulePreloads) as $modulePreload)
        <link rel="modulepreload" crossorigin href="{{ asset($modulePreload) }}">
      @endforeach
      @foreach (array_keys($stylesheets) as $stylesheet)
        <link rel="stylesheet" crossorigin href="{{ asset($stylesheet) }}">
      @endforeach
      <script type="module" crossorigin src="{{ asset($entry['file']) }}"></script>
    @endif
  </head>
  <body>
    @if ($frontendBuildReady)
      <script>
        // Apply dark mode immediately before render (default: dark)
        if (localStorage.getItem('flash-dark-mode') !== 'false') {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
      </script>
      <div id="app"></div>
      <script>
        if ('serviceWorker' in navigator) {
          window.addEventListener('load', async () => {
            try {
              const registration = await navigator.serviceWorker.register(@json($serviceWorkerUrl), {
                updateViaCache: 'none',
              });
              registration.update();
            } catch (error) {
              console.error('Service worker registration failed:', error);
            }
          });
        }
      </script>
    @else
      <main style="min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; background: #09090b; color: #f8fafc; font-family: Arial, sans-serif;">
        <section style="max-width: 760px; width: 100%; border: 1px solid rgba(255,255,255,0.12); border-radius: 18px; padding: 28px; background: rgba(15,23,42,0.84); box-shadow: 0 24px 60px rgba(0,0,0,0.35);">
          <p style="margin: 0 0 10px; font-size: 12px; letter-spacing: 0.12em; text-transform: uppercase; color: #38bdf8;">Deployment issue</p>
          <h1 style="margin: 0 0 12px; font-size: 28px; line-height: 1.2;">Frontend build is not available on this server yet.</h1>
          <p style="margin: 0 0 14px; color: #cbd5e1; line-height: 1.7;">{{ $deployHelp }}</p>
          <p style="margin: 0; color: #94a3b8; line-height: 1.7;">Expected file: <strong>{{ $assetManifestPath }}</strong></p>
        </section>
      </main>
    @endif
  </body>
</html>
