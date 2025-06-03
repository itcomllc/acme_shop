<?php
namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SSLController extends Controller
{
    public function downloadCertificate(Certificate $certificate)
    {
        if ($certificate->subscription->user_id !== Auth::id()) {
            abort(403);
        }

        if ($certificate->status !== 'issued') {
            abort(404, 'Certificate not ready for download');
        }

        $certificateData = $certificate->certificate_data;
        
        return response()->streamDownload(function () use ($certificateData) {
            echo $certificateData['certificate'];
        }, "certificate_{$certificate->domain}.pem", [
            'Content-Type' => 'application/x-pem-file'
        ]);
    }
}