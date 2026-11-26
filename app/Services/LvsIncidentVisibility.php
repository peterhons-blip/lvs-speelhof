<?php
namespace App\Services;
class LvsIncidentVisibility {
 public static function apply($query,string $column='vertrouwelijk'){
  if (!app(LibraryAccess::class)->has(request(),'lvs.meldingen.confidential.view')) $query->where($column,0);
  return $query;
 }
}
