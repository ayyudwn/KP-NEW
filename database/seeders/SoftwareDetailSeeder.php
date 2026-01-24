<?php

namespace Database\Seeders;

use App\Models\SoftwareDetail;
use App\Models\Course;
use Illuminate\Database\Seeder;

class SoftwareDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Software sesuai kebutuhan mata kuliah pada CourseSeeder
     */
    public function run(): void
    {
        $software = [
            // IDE & Code Editor - Untuk Pemrograman
            ['code' => 'VSCODE', 'nama' => 'Visual Studio Code', 'keterangan' => 'Code editor untuk Pemrograman Web, Struktur Data, dll'],
            ['code' => 'ANDROID_STUDIO', 'nama' => 'Android Studio', 'keterangan' => 'IDE untuk Proyek Aplikasi Mobile'],
            ['code' => 'INTELLIJ', 'nama' => 'IntelliJ IDEA', 'keterangan' => 'IDE untuk Pemrograman Berorientasi Objek (Java)'],

            // Web Development - Untuk Pemrograman Web
            ['code' => 'XAMPP', 'nama' => 'XAMPP', 'keterangan' => 'Apache + MySQL + PHP untuk Pemrograman Web Lanjut'],
            ['code' => 'NODEJS', 'nama' => 'Node.js', 'keterangan' => 'JavaScript runtime untuk Pemrograman Sisi Server'],
            ['code' => 'LARAGON', 'nama' => 'Laragon', 'keterangan' => 'Laravel development environment'],
            ['code' => 'POSTMAN', 'nama' => 'Postman', 'keterangan' => 'API testing untuk Pemrograman Web'],

            // Database - Untuk Basis Data
            ['code' => 'MYSQL', 'nama' => 'MySQL Workbench', 'keterangan' => 'Database management untuk Sistem Basis Data'],
            ['code' => 'DBEAVER', 'nama' => 'DBeaver', 'keterangan' => 'Universal database tool untuk Manajemen Basis Data'],
            ['code' => 'PHPMYADMIN', 'nama' => 'phpMyAdmin', 'keterangan' => 'Web-based MySQL administration'],

            // Game Development - Untuk Pemrograman Game
            ['code' => 'UNITY', 'nama' => 'Unity', 'keterangan' => 'Game engine untuk Pemrograman Game'],
            ['code' => 'GODOT', 'nama' => 'Godot Engine', 'keterangan' => 'Open source game engine'],
            ['code' => 'UNREAL', 'nama' => 'Unreal Engine', 'keterangan' => 'Game engine untuk Pemrograman Game'],

            // Design & Graphics - Untuk DKV (A14)
            ['code' => 'PHOTOSHOP', 'nama' => 'Adobe Photoshop', 'keterangan' => 'Image editing untuk Grafis Komputer, Desain Web'],
            ['code' => 'ILLUSTRATOR', 'nama' => 'Adobe Illustrator', 'keterangan' => 'Vector graphics untuk Grafis Komputer'],
            ['code' => 'FIGMA', 'nama' => 'Figma', 'keterangan' => 'UI/UX design untuk Desain Web'],
            ['code' => 'COREL', 'nama' => 'CorelDRAW', 'keterangan' => 'Vector graphics untuk Reprografika, Proyek Desain Kemasan'],

            // 3D Modeling - Untuk Pemodelan 3D, Animasi 3D
            ['code' => 'BLENDER', 'nama' => 'Blender', 'keterangan' => '3D modeling/animation untuk Pemodelan 3D, Animasi 3D'],
            ['code' => 'MAYA', 'nama' => 'Autodesk Maya', 'keterangan' => '3D animation untuk Animasi 3D I, Animasi 3D II'],
            ['code' => '3DSMAX', 'nama' => '3ds Max', 'keterangan' => '3D modeling untuk Pemodelan 3D I'],
            ['code' => 'ZBRUSH', 'nama' => 'ZBrush', 'keterangan' => 'Digital sculpting untuk Pemodelan 3D'],
            ['code' => 'CINEMA4D', 'nama' => 'Cinema 4D', 'keterangan' => 'Motion graphics untuk Grafika Gerak'],

            // 2D Animation - Untuk Animasi 2D
            ['code' => 'TOONBOOM', 'nama' => 'Toon Boom Harmony', 'keterangan' => '2D animation untuk Animasi 2D I'],
            ['code' => 'ANIMATE', 'nama' => 'Adobe Animate', 'keterangan' => '2D animation untuk Animasi 2D, Grafis Bergerak'],
            ['code' => 'CLIPSTUDIO', 'nama' => 'Clip Studio Paint', 'keterangan' => 'Digital illustration untuk Ilustrasi'],
            ['code' => 'PROCREATE', 'nama' => 'Procreate', 'keterangan' => 'Digital illustration untuk Ilustrasi'],

            // Video Editing - Untuk Video Editing, Film & Televisi
            ['code' => 'AFTEREFFECT', 'nama' => 'Adobe After Effects', 'keterangan' => 'Motion graphics untuk Efek Visual 2D, Efek Visual 3D'],
            ['code' => 'DAVINCI', 'nama' => 'DaVinci Resolve', 'keterangan' => 'Video editing & color grading'],
            ['code' => 'AUDITION', 'nama' => 'Adobe Audition', 'keterangan' => 'Audio editing untuk Tata Suara Pemutaran Film'],

            // Rigging - Untuk Rigging 2D
            ['code' => 'SPINE', 'nama' => 'Spine', 'keterangan' => '2D skeletal animation untuk Rigging 2D'],
            ['code' => 'DRAGONBONES', 'nama' => 'DragonBones', 'keterangan' => '2D rigging tool untuk Rigging 2D'],

            // AI Tools - Untuk Kecerdasan Artifisial Kreatif
            ['code' => 'PYTHON', 'nama' => 'Python', 'keterangan' => 'Programming language untuk Kecerdasan Artifisial Kreatif'],
            ['code' => 'JUPYTER', 'nama' => 'Jupyter Notebook', 'keterangan' => 'Interactive computing untuk AI/ML'],
            ['code' => 'COMFYUI', 'nama' => 'ComfyUI', 'keterangan' => 'AI image generation untuk Kecerdasan Artifisial Kreatif'],
            ['code' => 'STABLEDIFF', 'nama' => 'Stable Diffusion', 'keterangan' => 'AI image generation'],

            // Multimedia - Untuk A22
            ['code' => 'FLASH', 'nama' => 'Adobe Flash/Animate', 'keterangan' => 'Multimedia authoring untuk Multimedia'],
            ['code' => 'EXCEL', 'nama' => 'Microsoft Excel', 'keterangan' => 'Spreadsheet'],
            ['code' => 'POWERPOINT', 'nama' => 'Microsoft PowerPoint', 'keterangan' => 'Presentation'],

            // Browser
            ['code' => 'CHROME', 'nama' => 'Google Chrome', 'keterangan' => 'Web browser untuk testing web'],
            ['code' => 'FIREFOX', 'nama' => 'Mozilla Firefox', 'keterangan' => 'Web browser untuk development'],

            // Version Control
            ['code' => 'GIT', 'nama' => 'Git', 'keterangan' => 'Version control untuk semua mata kuliah pemrograman'],
        ];

        foreach ($software as $item) {
            SoftwareDetail::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }

        $this->command->info('SoftwareDetail seeder completed: ' . count($software) . ' software.');
    }
}
