<?php

declare(strict_types=1);

namespace App\Admin;

final class ResourceRegistry
{
    public static function get(string $resource): ?array
    {
        $resources = [
            'experiences' => [
                'table' => 'experiences',
                'fields' => ['organization_id','slug','role_title','start_date','end_date','is_current','location','work_mode','employment_type','summary','description','highlights','featured','sort_order','is_visible'],
                'required' => ['organization_id','slug','role_title','start_date'],
            ],
            'projects' => [
                'table' => 'projects',
                'fields' => ['organization_id','experience_id','slug','name','period_label','context','participation','solution','architecture','challenges','results','repository_url','external_url','cover_image_url','featured','sort_order','status'],
                'required' => ['slug','name'],
                'upload_fields' => ['cover_image_url'],
                'dependent_uploads' => [
                    ['table' => 'project_media', 'field' => 'media_url', 'foreign_key' => 'project_id'],
                ],
            ],
            'technologies' => [
                'table' => 'technologies',
                'fields' => ['slug','name','category','description','is_current','sort_order','is_visible'],
                'required' => ['slug','name','category'],
            ],
            'education' => [
                'table' => 'education',
                'fields' => ['institution','course','degree_type','start_date','end_date','location','logo_url','certificate_url','description','sort_order','is_visible'],
                'required' => ['institution','course'],
                'upload_fields' => ['logo_url','certificate_url'],
            ],
            'certifications' => [
                'table' => 'certifications',
                'fields' => ['name','issuer','logo_url','issued_date','workload_hours','credential_url','description','sort_order','is_visible'],
                'required' => ['name','issuer'],
                'upload_fields' => ['logo_url','credential_url'],
            ],
            'publications' => [
                'table' => 'publications',
                'fields' => ['slug','title','kind','organization','event_name','location','published_date','summary','description','external_url','sort_order','is_visible'],
                'required' => ['slug','title','kind'],
                'dependent_uploads' => [
                    ['table' => 'publication_media', 'field' => 'media_url', 'foreign_key' => 'publication_id'],
                ],
            ],
            'organizations' => [
                'table' => 'organizations',
                'fields' => ['slug','name','website_url','image_url','description','sort_order'],
                'required' => ['slug','name'],
                'upload_fields' => ['image_url'],
            ],
            'links' => [
                'table' => 'site_links',
                'fields' => ['label','url','kind','sort_order','is_visible'],
                'required' => ['label','url','kind'],
            ],
            'project-links' => [
                'table' => 'project_links',
                'fields' => ['project_id','label','url','kind','sort_order','is_visible'],
                'required' => ['project_id','label','url','kind'],
            ],
            'project-media' => [
                'table' => 'project_media',
                'fields' => ['project_id','media_url','alt_text','caption','kind','sort_order','is_visible'],
                'required' => ['project_id','media_url','alt_text'],
                'upload_fields' => ['media_url'],
            ],
            'project-relations' => [
                'table' => 'project_relations',
                'order_by' => 'project_id, sort_order, id',
                'fields' => ['project_id','entity_type','entity_id','relation_label','sort_order','is_visible'],
                'required' => ['project_id','entity_type','entity_id'],
                'allowed_values' => [
                    'entity_type' => ['experience','education','certification'],
                ],
            ],
            'project-sections' => [
                'table' => 'project_sections',
                'fields' => ['project_id','eyebrow','title','body','items','sort_order','is_visible'],
                'required' => ['project_id','title'],
            ],
            'publication-media' => [
                'table' => 'publication_media',
                'fields' => ['publication_id','title','media_type','media_url','embed_url','sort_order','is_visible'],
                'required' => ['publication_id','media_type','media_url'],
                'upload_fields' => ['media_url'],
            ],
            'publication-sections' => [
                'table' => 'publication_sections',
                'fields' => ['publication_id','eyebrow','title','body','items','sort_order','is_visible'],
                'required' => ['publication_id','title'],
            ],
            'publication-projects' => [
                'table' => 'publication_projects',
                'fields' => ['publication_id','project_id','relation_label','sort_order','is_visible'],
                'required' => ['publication_id','project_id'],
            ],
            'pages' => [
                'table' => 'pages',
                'order_by' => 'sort_order, id',
                'fields' => ['title','slug','status','page_kind','entity_type','entity_id','seo_title','seo_description','canonical_url','og_image_url','noindex','show_in_sitemap','sort_order'],
                'required' => ['title','slug','status'],
                'upload_fields' => ['og_image_url'],
                'dependent_uploads' => [
                    ['table' => 'page_sections', 'field' => 'image_url', 'foreign_key' => 'page_id'],
                ],
            ],
            'page-sections' => [
                'table' => 'page_sections',
                'order_by' => 'page_id, sort_order, id',
                'fields' => ['page_id','section_type','internal_name','eyebrow','title','accent_title','lead','body','image_url','image_alt','cta_label','cta_url','theme','background_color','text_color','accent_color','width_mode','spacing_top','spacing_bottom','settings','sort_order','is_visible'],
                'required' => ['page_id','section_type'],
                'upload_fields' => ['image_url'],
                'dependent_uploads' => [
                    ['table' => 'page_section_items', 'field' => 'image_url', 'foreign_key' => 'section_id'],
                ],
            ],
            'page-section-items' => [
                'table' => 'page_section_items',
                'order_by' => 'section_id, sort_order, id',
                'fields' => ['section_id','item_type','entity_type','entity_id','eyebrow','title','subtitle','body','value_label','url','image_url','image_alt','settings','sort_order','is_visible'],
                'required' => ['section_id','item_type'],
                'upload_fields' => ['image_url'],
            ],
            'site-navigation' => [
                'table' => 'site_navigation',
                'order_by' => "CASE location WHEN 'header' THEN 0 WHEN 'footer' THEN 1 ELSE 2 END, sort_order, id",
                'fields' => ['location','label','page_id','url','is_external','sort_order','is_visible'],
                'required' => ['location','label'],
            ],
            'site-settings' => [
                'table' => 'site_settings',
                'fields' => ['header_logo_url','header_logo_alt','footer_logo_url','footer_logo_alt','footer_lead','footer_title','footer_body'],
                'required' => [],
                'upload_fields' => ['header_logo_url','footer_logo_url'],
                'order_by' => 'id',
            ],
            'experience-technologies' => [
                'table' => 'experience_technology',
                'fields' => ['experience_id','technology_id','sort_order'],
                'required' => ['experience_id','technology_id'],
            ],
            'project-technologies' => [
                'table' => 'project_technology',
                'fields' => ['project_id','technology_id','sort_order'],
                'required' => ['project_id','technology_id'],
            ],
            'evaluation-boards' => [
                'table' => 'evaluation_boards',
                'fields' => ['slug','title','institution','logo_url','event_date','description','sort_order','is_visible'],
                'required' => ['slug','title','institution','event_date'],
                'upload_fields' => ['logo_url'],
            ],
        ];

        return $resources[$resource] ?? null;
    }

    public static function uploadReferences(): array
    {
        $references = [];
        foreach (self::all() as $resource) {
            $config = self::get($resource);
            foreach ($config['upload_fields'] ?? [] as $field) {
                $references[] = ['table' => $config['table'], 'field' => $field];
            }
        }
        return $references;
    }

    public static function all(): array
    {
        return [
            'experiences','projects','technologies','education','certifications','publications','organizations','links',
            'project-links','project-media','project-relations','project-sections','publication-media','publication-sections',
            'publication-projects','pages','page-sections','page-section-items','site-navigation','site-settings',
            'experience-technologies','project-technologies','evaluation-boards',
        ];
    }
}
