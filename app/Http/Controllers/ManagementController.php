<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class ManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $tab    = $request->get('tab', 'admin');

        $query = fn(string $role) => User::where('role', $role)
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

        $departments = Department::withCount(['operators' => fn($q) => $q->where('role', 'operator')])
            ->orderBy('name')
            ->get();

        return view('management.index', [
            'developers'  => $query('developer'),
            'admins'      => $query('admin'),
            'supervisors' => $query('supervisor'),
            'mandors'     => $query('mandor'),
            'operators'   => $query('operator'),
            'visitors'    => $query('visitor'),
            'departments' => $departments,
            'tab'         => in_array($tab, ['developer', 'admin', 'supervisor', 'mandor', 'operator', 'visitor', 'department']) ? $tab : 'admin',
            'search'      => $search,
        ]);
    }
}
