<?php

namespace App\Http\Controllers;

use App\Association;
use App\Division;
use App\Http\Requests\AssociationCreateRequest;
use App\Http\Requests\AssociationUpdateRequest;
use App\Series;
use App\Venue;
use Bouncer;

class AssociationsController extends Controller
{
    public function view(Association $association)
    {
        return view('association.view', ['association' => $association]);
    }

    public function edit(Association $association)
    {
        if (Bouncer::can('edit', $association)) {
            return view('association.edit', [
                'association' => $association,
                'series' => Series::where('association_id', $association->id)->get(),
                'divisions' => Division::orderBy('sequence', 'ASC')->where('association_id', $association->id)->get(),
                'venues' => Venue::orderBy('name', 'ASC')->where('association_id', $association->id)->get(),
                'current_user' => \Auth::user(),
            ]);
        } else {
            return view('denied');
        }
    }

    /**
     * Store a new association.
     *
     * @return Response
     */
    public function store(AssociationCreateRequest $request)
    {
        if (Bouncer::can('create', Association::class)) {
            $association = new Association;

            $association->name = $request->name;
            $association->user_id = $request->user_id;

            $association->save();

            // TODO: Do not necessarily "onboard" for certain roles?
            return redirect()->route('onboard.association', ['association' => $association]);
        } else {
            return view('denied');
        }
    }

    public function update(AssociationUpdateRequest $request)
    {
        $association = Association::find($request->id);

        $association->name = $request->name;
        $association->user_id = $request->user_id;

        if ($request->filled('subdomain') && Bouncer::can('administer-subdomains')) {
            $association->subdomain = $request->subdomain;
        }

        if (isset($request->home_image_file)) {
            $path = $request->home_image_file->storeAs(
                'home_image_file/'.$association->subdomain, $request->home_image_file->hashName(), 'public'
            );

            $association->home_image_path = $path;
        }

        if (isset($request->rules_file)) {
            $path = $request->rules_file->storeAs(
                'rules_file/'.$association->subdomain, $request->rules_file->hashName(), 'public'
            );

            $association->rules_file_path = $path;
        }

        if ($request->hasFile('favicon')) {
            $faviconDir = \Storage::disk('public')->path('favicon/'.$association->subdomain);

            if (! is_dir($faviconDir)) {
                mkdir($faviconDir, 0755, true);
            }

            $this->extractFaviconArchive($request->favicon->getPathname(), $faviconDir);
        }

        $association->favicon_metadata = \Purifier::clean($request->favicon_metadata, 'favicon_metadata');

        $association->about = \Purifier::clean($request->about, 'about');

        $association->venues_label_override = $request->venues_label_override;

        $association->save();

        // Session::flash('message', 'Successfully updated nerd!');

        $url = $request->url;

        if (! empty($url)) {
            return redirect($url)->with('success', 'Data saved successfully!');
        }

        return redirect()->route('user', ['user' => \Auth::user()->id]);

    }

    /**
     * Extract a favicon zip into $destDir only if every entry is a safe,
     * allow-listed filename with no path-traversal segments. If any entry
     * fails validation, nothing is extracted.
     */
    private function extractFaviconArchive(string $zipPath, string $destDir): void
    {
        $allowedExtensions = ['ico', 'png', 'svg', 'json', 'xml', 'webmanifest'];

        $zip = new \ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false
                || str_contains($entryName, '..')
                || str_starts_with($entryName, '/')
                || str_contains($entryName, '\\')
            ) {
                $zip->close();

                return;
            }

            // Directory entries (e.g. "icons/") are safe to create, they carry no extension.
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

            if (! in_array($extension, $allowedExtensions, true)) {
                $zip->close();

                return;
            }
        }

        $zip->extractTo($destDir);
        $zip->close();
    }

    public function create()
    {
        if (Bouncer::can('create', Association::class)) {
            return view('association.create', ['current_user' => \Auth::user()]);
        } else {
            return view('denied');
        }
    }

    public function deleteConfirm(Association $association)
    {
        return view('association.delete', ['association' => $association]);
    }

    public function delete(Association $association)
    {
        $association->delete();

        return redirect()->route('admin')->with('success', 'Association deleted successfully.');
    }

    public function undeleteConfirm(Association $association)
    {
        return view('association.undelete', ['association' => $association]);
    }

    public function undelete(Association $association)
    {
        $association->restore();

        return redirect()->route('user', ['user' => \Auth::user()])->with('success', 'Association restored successfully.');
    }

    public function rulesDelete(Association $association)
    {
        $association->rules_file_path = null;

        $association->save();

        return view('association.edit', [
            'association' => $association,
            'current_user' => \Auth::user(),
        ]);
    }

    public function homeImageDelete(Association $association)
    {
        $association->home_image_path = null;

        $association->save();

        return view('association.edit', [
            'association' => $association,
            'current_user' => \Auth::user(),
        ]);
    }

    public function deleted()
    {
        if (Bouncer::can('administer-associations')) {
            $associations = Association::onlyTrashed()->get();

            return view('admin.associations.trashed', ['associations' => $associations]);
        } else {
            return view('denied');
        }
    }
}
