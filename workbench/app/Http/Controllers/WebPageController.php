<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Models\Blog;
use Foundry\Models\SupportTicket;
use Foundry\Rules\ReCaptchaRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebPageController extends Controller
{
    public function index(Request $request)
    {
        if ($view = settings('reading.homepage.view')) {
            return view("pages.$view", $request->input());
        }

        return view('pages.home', $request->input());
    }

    public function blogs(Request $request)
    {
        try {
            return view('pages.blogs', $request->input());
        } catch (\Throwable $e) {
            return abort(404);
        }
    }

    public function blog(Request $request, $slug)
    {
        $blog = Cache::rememberForever("blog_{$slug}", function () use ($slug) {
            return Blog::findBySlug($slug);
        });

        $request->merge(['blog' => $blog]);

        try {
            return view('pages.blog', $blog);
        } catch (\Throwable $e) {
            return abort(404);
        }
    }

    public function contact(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            'subject' => 'required|string',
            'phone' => 'required|string',
            'message' => 'required|string',
            'recaptcha_token' => ['required', new ReCaptchaRule],
        ]);

        $data = $request->only(['email', 'name', 'subject', 'phone', 'message']);

        Foundry::$supportTicketModel::create($data + [
            'source' => SupportTicket::SOURCE_CONTACT_US,
        ]);

        return redirect()->back()->with('success', 'Your support ticket has been submitted successfully.');
    }
}
