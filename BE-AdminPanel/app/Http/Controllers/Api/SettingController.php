<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Repository\SettingRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class SettingController extends Controller
{
    private $settingRepository;
    private const DEFAULT_TERMS = '<h1>Syarat dan Ketentuan</h1><p>Konten syarat dan ketentuan belum tersedia. Silakan hubungi admin untuk informasi lebih lanjut.</p>';
    private const DEFAULT_PRIVACY = '<h1>Kebijakan Privasi</h1><p>Konten kebijakan privasi belum tersedia. Silakan hubungi admin untuk informasi lebih lanjut.</p>';

    public function __construct(
        SettingRepositoryInterface $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json($this->settingRepository->all(
            ['*'], ['currency']
        )->first(), 200);
    }

    public function update(Request $request)
    {

        //validate the request
        $this->validate($request, [
            'commission' => 'required|numeric|gt:0',
            'currency_id' => 'required|integer',
            'rate_per_km' => 'required|numeric|gt:0',
            'publish_trips_future_days' => 'required|integer|gt:0',
            'max_distance_to_stop' => 'required|numeric|gt:0',
            'distance_to_stop_to_mark_arrived' => 'required|numeric|gt:0',
            'allow_ads_in_driver_app' => 'boolean',
            'allow_ads_in_customer_app' => 'boolean',
            'allow_seat_selection' => 'boolean',
        ], [], []);


        $settings = $this->settingRepository->all()->first();

        $newSettings = [
            'commission' => $request->commission,
            'currency_id' => $request->currency_id,
            'rate_per_km' => $request->rate_per_km,
            'publish_trips_future_days' => $request->publish_trips_future_days,
            'max_distance_to_stop' => $request->max_distance_to_stop,
            'distance_to_stop_to_mark_arrived' => $request->distance_to_stop_to_mark_arrived,
            'allow_ads_in_driver_app' => $request->allow_ads_in_driver_app ?? false,
            'allow_ads_in_customer_app' => $request->allow_ads_in_customer_app ?? false,
            'allow_seat_selection' => $request->allow_seat_selection ?? false,
        ];
        $this->settingRepository->update($settings->id, $newSettings);
    }

    public function getPrivacyPolicy(Request $request)
    {
        $privacy = $this->readPublicHtml('privacy_local.html', self::DEFAULT_PRIVACY);

        return response()->json(['privacy' => $privacy]);
    }

    public function getPrivacy(Request $request)
    {
        $privacy = $this->readPublicHtml('privacy.html', self::DEFAULT_PRIVACY);

        return response()->json(['privacy' => $privacy]);
    }


    public function updatePrivacyPolicy(Request $request)
    {
        //validate the request
        $validator = Validator::make($request->all(), [
            'privacy' => 'required|string',
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        //privacy_local
        file_put_contents(public_path('privacy_local.html'), $request->privacy);

        //add html headers
        $request->privacy = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Privacy Policy</title></head><body>' . $request->privacy . '</body></html>';

        file_put_contents(public_path('privacy.html'), $request->privacy);

        return response()->json(['success' => ['Privacy Policy updated successfully']]);
    }

    public function updateTerms(Request $request)
    {
        //validate the request
        $validator = Validator::make($request->all(), [
            'terms' => 'required|string',
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        file_put_contents(public_path('terms_local.html'), $request->terms);

        //add html headers
        $request->terms = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Terms and Conditions</title></head><body>' . $request->terms . '</body></html>';

        file_put_contents(public_path('terms.html'), $request->terms);

        return response()->json(['success' => ['Terms updated successfully']]);
    }

    public function getTerms(Request $request)
    {
        //get terms
        $terms = $this->readPublicHtml('terms_local.html', self::DEFAULT_TERMS);

        return response()->json(['terms' => $terms]);
    }

    private function readPublicHtml(string $filename, string $fallback): string
    {
        $path = public_path($filename);

        if (!file_exists($path) || !is_readable($path)) {
            Log::warning('Public document file is not readable.', ['path' => $path]);
            return $fallback;
        }

        $contents = file_get_contents($path);
        return $contents === false ? $fallback : $contents;
    }
}
