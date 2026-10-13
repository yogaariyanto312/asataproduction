<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\Changelog;
use App\Models\User;
use App\Support\AplikasiAndroid;
use App\Support\MenuAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Tutorial & Tentang Aplikasi (dulu closure di routes/web.php; dipakai juga API Android). */
class InfoController extends Controller
{
    public function tutorial()
    {
        return Inertia::render('Tutorial', [
            'iframeUrl'   => BotSetting::instance()->tutorial_iframe_url,
            // Diatur lewat Hak Akses Menu (tutorial.edit), bukan dikunci developer.
            'canEdit'     => MenuAccess::can(auth()->user(), 'tutorial.edit'),
            'embedAction' => route('tutorial.embed.update'),
            'panduanUrl'  => asset('panduan/index.html'),
            'chatUrl'     => MenuAccess::can(auth()->user(), 'chatting') ? route('chatting') : null,
        ]);
    }

    public function tutorialEmbed(Request $request)
    {
        // Izinnya dijaga Hak Akses Menu (tutorial.edit) lewat route ini — dulu
        // dikunci developer di sini, sehingga saklar di Hak Akses tidak ada artinya.
        $request->validate(['iframe_url' => ['nullable', 'string', 'max:1000']]);
        BotSetting::instance()->update(['tutorial_iframe_url' => $request->iframe_url ?: null]);

        return back()->with('success', 'URL embed berhasil diperbarui.');
    }

    /** About — developer pertama yang aktif + changelog. */
    public function about()
    {
        $developer  = User::where('role', 'developer')->where('is_active', true)->first();
        // id ikut menentukan urutan: beberapa entri bisa lahir di detik yang sama.
        $changelogs = Changelog::latest()->latest('id')->get();

        // Username GitHub dari link_github (https://github.com/username → username).
        $github = $developer?->link_github ? basename(rtrim($developer->link_github, '/')) : null;

        return Inertia::render('About', [
            // Versi aplikasi mengikuti entri Riwayat Update terbaru, bukan angka tetap.
            'versi'      => Changelog::versiAplikasi(),
            // APK Android terbaru + QR menuju link unduhnya (null = belum diunggah).
            'aplikasi'   => AplikasiAndroid::kartu(),
            // Installer aplikasi Windows terbaru (null = belum diunggah).
            'aplikasiWindows' => AplikasiAndroid::info('windows'),
            'year'       => now()->year,
            'githubUser' => $github,
            'canManage'  => auth()->user()->isDeveloper(),
            'storeUrl'   => route('about.changelog.store'),
            'baseUrl'    => url('/about/changelog'),
            'developer'  => $developer ? [
                'name'      => $developer->name,
                'handle'    => $developer->handle,
                'bio'       => $developer->bio,
                'avatar'    => $developer->about_avatar
                    ? route('storage.file', ['path' => $developer->about_avatar])
                    : $developer->avatarUrl(),
                'instagram' => $developer->link_instagram,
                'github'    => $developer->link_github,
                'portfolio' => $developer->link_portfolio,
                'email'     => $developer->link_email,
            ] : null,
            'changelogs' => $changelogs->map(fn ($c) => [
                'id'          => $c->id,
                'type'        => $c->type,
                'version'     => $c->version,
                'title'       => $c->title,
                'description' => $c->description,
                'date'        => $c->created_at?->timezone('Asia/Jakarta')->locale('id')->isoFormat('DD MMM YYYY'),
            ])->values(),
        ]);
    }

    public function changelogStore(Request $request)
    {
        $request->validate([
            'type'        => ['required', 'in:feature,fix,improvement,security'],
            'version'     => ['nullable', 'string', 'max:20'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        Changelog::create($request->only('type', 'version', 'title', 'description'));

        return back()->with('success', 'Entry changelog berhasil ditambahkan.');
    }

    public function changelogDestroy(Changelog $changelog)
    {
        $changelog->delete();

        return back()->with('success', 'Entry changelog berhasil dihapus.');
    }

    /** APK terbaru; nama berkas memuat versi supaya tidak tertukar dengan unduhan lama. */
    public function unduhAplikasi()
    {
        $info = AplikasiAndroid::info();
        abort_unless($info, 404, 'Aplikasi Android belum tersedia.');

        return response()->download(
            AplikasiAndroid::berkas($info['berkas']),
            'AsataProduction-'.$info['versionName'].'.apk',
            ['Content-Type' => 'application/vnd.android.package-archive', 'Cache-Control' => 'no-cache'],
        );
    }

    /** Installer MSI aplikasi Windows terbaru. */
    public function unduhAplikasiWindows()
    {
        $info = AplikasiAndroid::info('windows');
        abort_unless($info, 404, 'Aplikasi Windows belum tersedia.');

        return response()->download(
            AplikasiAndroid::berkas($info['berkas'], 'windows'),
            'AsataProduction-'.$info['versionName'].'.msi',
            ['Content-Type' => 'application/x-msi', 'Cache-Control' => 'no-cache'],
        );
    }

    /** Dipakai fitur auto update aplikasi Android (bawaan) dan Windows (?platform=windows). */
    public function versiAplikasi(Request $request)
    {
        return response()
            ->json(['success' => true, 'data' => AplikasiAndroid::info(AplikasiAndroid::platform($request->query('platform')))])
            ->header('Cache-Control', 'no-store');
    }
}
