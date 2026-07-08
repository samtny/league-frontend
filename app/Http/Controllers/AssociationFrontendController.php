<?php

namespace App\Http\Controllers;

class AssociationFrontendController extends AssociationAwareController
{
    public function home()
    {
        if (! empty($this->association)) {
            return view('association.home', ['association' => $this->association]);
        } else {
            abort(404);
        }
    }

    public function about()
    {
        return view('association.about', ['association' => $this->association]);
    }

    public function rules()
    {
        return view('association.rules', ['association' => $this->association]);
    }

    public function css()
    {
        $content = '';

        if (! empty($this->association) && ! empty($this->association->subdomain)) {
            $path = public_path('css/association/'.$this->association->subdomain.'.css');

            if (file_exists($path)) {
                $content = file_get_contents($path);
            }
        }

        $response = \Response::make($content);
        $response->header('Content-Type', 'text/css');
        $response->header('Cache-Control', 'public, max-age=300, must-revalidate');

        return $response;
    }
}
