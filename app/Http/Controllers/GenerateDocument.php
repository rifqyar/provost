<?php

namespace App\Http\Controllers;

use App\Models\Agama;
use App\Models\DataPelanggar;
use App\Models\DokumenPelanggar;
use App\Models\Penyidik;
use App\Models\Sp2hp2History;
use App\Models\SprinHistory;
use App\Models\Witness;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class GenerateDocument extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadFile($filename)
    {
        $path = storage_path('document/'.$filename);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function generateDisposisi(Request $request)
    {
        $kasus = DataPelanggar::find($request->kasus_id);

        $data = [
            'tanggal' => $request->tanggal,
            'surat_dati' => $request->surat_dari,
            'nomor_surat' => $request->nomor_surat,
            'perihal' => $kasus->perihal_nota_dinas,
            'nomor_agenda' => $request->nomor_agenda
        ];

        $filename = 'disposisi-'.$data['nomor_surat'].'-'.$data['tanggal'];
        $path = storage_path('document/'.$filename.'.docx');
        $template = new TemplateProcessor(storage_path('template/template disposisi.docx'));

        $template->setValue('no_surat', $data['nomor_surat']);
        $template->setValue('no_agenda', $data['nomor_agenda']);
        $template->setValue('surat_dati', $data['surat_dati']);
        $template->setValue('tanggal', $data['tanggal']);
        $template->setValue('perihal', $data['perihal']);

        $dokumen = DokumenPelanggar::where('data_pelanggar_id', $request->kasus_id)->where('process_id', $request->process_id)->where('sub_process_id', null)->first();
        if($dokumen == null){
            DokumenPelanggar::create([
                'data_pelanggar_id' => $request->kasus_id,
                'process_id' => 2,
                'sub_process_id' => null,
                'created_by' => Auth::user()->id,
                'status' => 1
            ]);
        }

        $data = DataPelanggar::find($request->kasus_id);
        if($data->status_id < 2){
            $data->status_id = 2;
            $data->save();
        }

        $template->saveAs($path);
        return response()->json(['file' => $filename.'.docx']);

        // Convert PDF (Kalau butuh nanti)
        // $pdfPath = storage_path('app/public/document/disposisi/');
        // $convert='"C:/Program Files/LibreOffice/program/soffice" --headless --convert-to pdf "'.$path.'" --outdir "'.$pdfPath.'"';
        // $result=exec($convert);
    }

    public function generateDisposisiKaro(Request $request){
        // DokumenPelanggar::create([
        //     'data_pelanggar_id' => $request->kasus_id,
        //     'process_id' => 2,
        //     'sub_process_id' => 1,
        //     'created_by' => Auth::user()->id,
        //     'status' => 1
        // ]);
        return redirect()->back()->with('msg', 'Proses cetak Disposisi Karo sedang dalam pengerjaan');
    }

    public function generateDisposisiRikum(Request $request){
        // DokumenPelanggar::create([
        //     'data_pelanggar_id' => $request->kasus_id,
        //     'process_id' => 2,
        //     'sub_process_id' => 2,
        //     'created_by' => Auth::user()->id,
        //     'status' => 1
        // ]);
        return redirect()->back()->with('msg', 'Proses cetak Disposisi Rikum sedang dalam pengerjaan');
    }

    // Document Pulbaket
    public function SuratPerintah(Request $request, $kasus_id)
    {
        $kasus = DataPelanggar::find($kasus_id);
        $sprinHistory = SprinHistory::where('data_pelanggar_id', $kasus_id)->where('type', 'lidik')->first();
        $penyelidik = Penyidik::where('data_pelanggar_id', $kasus_id)->get();
        if ($sprinHistory == null){
            $sprinHistory = SprinHistory::create([
                'data_pelanggar_id' => $kasus_id,
                'no_sprin' => $request->no_sprin,
                'created_by' => Auth::user()->id,
                'type' => 'lidik'
            ]);


            $dokumen = DokumenPelanggar::where('data_pelanggar_id', $kasus_id)->where('process_id', $request->process_id)->where('sub_process_id', $request->sub_process)->first();
            if($dokumen == null){
                DokumenPelanggar::create([
                    'data_pelanggar_id' => $kasus_id,
                    'process_id' => $request->process_id,
                    'sub_process_id' => $request->sub_process,
                    'created_by' => Auth::user()->id,
                    'status' => 1
                ]);
            }
        }

        $template_document = new TemplateProcessor(storage_path('template\template_sprin.docx'));
        if (count($penyelidik) == 0){
            for ($i=0; $i < count($request->nama); $i++) {
                Penyidik::create([
                    'data_pelanggar_id' => $kasus_id,
                    'name' => strtoupper($request->nama[$i]),
                    'nrp' => $request->nrp[$i],
                    'pangkat' => strtoupper($request->pangkat[$i]),
                    'jabatan' => strtoupper($request->jabatan[$i]),
                    'kesatuan' => strtoupper($request->kesatuan[$i]),
                ]);
            }

            $template_document->cloneRow('pangkat_penyelidik', count($request->jabatan));

            for ($i=0; $i < count($request->jabatan); $i++) {
                $template_document->setValues(array(
                    "no#".$i+1 => $i+1,
                    'pangkat_penyelidik#'.$i+1 => strtoupper($request->pangkat[$i]),
                    'jabatan_penyelidik#'.$i+1 => strtoupper($request->jabatan[$i]),
                    'nama_penyelidik#'.$i+1 => strtoupper($request->nama[$i]),
                    'kesatuan_penyelidik#'.$i+1 => strtoupper($request->kesatuan[$i]),
                    'nrp_penyelidik#'.$i+1 => $request->nrp[$i]
                ));
            }
        } else {
            $template_document->cloneRow('pangkat_penyelidik', count($penyelidik));

            foreach ($penyelidik as $i => $val) {
                $template_document->setValues(array(
                    "no#".$i+1 => $i+1,
                    'pangkat_penyelidik#'.$i+1 => strtoupper($val->pangkat),
                    'jabatan_penyelidik#'.$i+1 => strtoupper($val->jabatan),
                    'kesatuan_penyelidik#'.$i+1 => strtoupper($val->kesatuan),
                    'nama_penyelidik#'.$i+1 => strtoupper($val->name),
                    'nrp_penyelidik#'.$i+1 => $val->nrp,
                ));
            }
        }

        $template_document->setValues(array(
            'no_nd' => $kasus->no_nota_dinas,
            'tgl_nd' => Carbon::parse($kasus->created_at)->translatedFormat('d F Y'),
            'perihal_nd' => $kasus->perihal_nota_dinas,
            'pelapor' => $kasus->pelapor,
            'wujud_perbuatan' => $kasus->wujud_perbuatan,
            'terlapor' => $kasus->terlapor,
            'pangkat' => $kasus->pangkat,
            'jabatan' => $kasus->jabatan,
            'kesatuan' => $kasus->kesatuan,
            'no_sprin' => $request->no_sprin != '' ? $request->no_sprin : $sprinHistory->no_sprin,
            'tanggal_ttd' => Carbon::parse($sprinHistory->created_at)->translatedFormat('F Y')
        ));

        $filename = 'Surat Perintah'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);
        if ($request->method() == 'GET'){
            return response()->download($path)->deleteFileAfterSend(true);
        } else {
            return response()->json(['file' => $filename]);
        }
    }

    public function SuratPerintahPengantar($kasus_id){
        $kasus = DataPelanggar::find($kasus_id);

        $template_document = new TemplateProcessor(storage_path('template\pengantar_sprin.docx'));
        $template_document->setValues(array(
            'nrp' => $kasus->nrp,
            'tgl_nd' => Carbon::parse($kasus->created_at)->translatedFormat('d F Y'),
            'kronologi' => $kasus->kronologi,
            'pangkat' => $kasus->pangkat,
            'terlapor' => $kasus->terlapor,
            'jabatan' => $kasus->jabatan,
            'kesatuan' => $kasus->kesatuan,
            'tgl_ttd' => Carbon::now()->translatedFormat('F Y')
        ));

        $filename = 'Surat Pengantar SPRIN-'.$kasus_id;
        $path = storage_path('document/'.$filename.'.docx');
        $template_document->saveAs($path);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function sp2hp(Request $request, $kasus_id, $generated){
        $template_document = new TemplateProcessor(storage_path('template\template_sp2hp.docx'));
        $filename = 'SP2HP'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);

        // $kasus = DataPelanggar::find($kasus_id);
        // $sp2hp2 = Sp2hp2History::where('data_pelanggar_id', $kasus_id)->first();
        // if (!$sp2hp2){
        //     $sp2hp2 = Sp2hp2History::create([
        //         'data_pelanggar_id' => $kasus_id,
        //         'penangan' => $request->penangan,
        //         'dihubungi' => $request->dihubungi,
        //         'jabatan_dihubungi' => $request->jabatan_dihubungi,
        //         'telp_dihubungi' => $request->telp_dihubungi,
        //         'created_by' => Auth::user()->id,
        //     ]);

        //     $dokumen = DokumenPelanggar::where('data_pelanggar_id', $kasus_id)->where('process_id', $request->process_id)->where('sub_process_id', $request->sub_process)->first();
        //     if($dokumen == null){
        //         DokumenPelanggar::create([
        //             'data_pelanggar_id' => $kasus_id,
        //             'process_id' => $request->process_id,
        //             'sub_process_id' => $request->sub_process,
        //             'created_by' => Auth::user()->id,
        //             'status' => 1
        //         ]);
        //     }
        // }
        // $template_document = new TemplateProcessor(storage_path('template\sp2hp2_awal.docx'));

        // $template_document->setValues(array(
        //     'penangan' => $sp2hp2->penangan,
        //     'dihubungi' => $sp2hp2->dihubungi,
        //     'jabatan_dihubungi' => $sp2hp2->jabatan_dihubungi,
        //     'telp_dihubungi' => $sp2hp2->telp_dihubungi,
        //     'pelapor' => $kasus->pelapor,
        //     'alamat' => $kasus->alamat,
        //     'bulan_tahun' => Carbon::parse($sp2hp2->created_at)->translatedFormat('F Y'),
        //     'tanggal' => Carbon::parse($kasus->created_at)->translatedFormat('d F Y'),
        //     'no_nota_dinas' => $kasus->no_nota_dinas,
        // ));


        // $filename = 'Surat SP2HP Awal'.'.docx';
        // $path = storage_path('document/'.$filename);
        // $template_document->saveAs($path);

        // if ($generated == 'generated'){
        //     return response()->download($path)->deleteFileAfterSend(true);
        // } else {
        //     return response()->json(['file' => $filename]);
        // }
    }

    public function sp2hp2_akhir($kasus_id, $process_id, $subprocess){
        return redirect()->back()->with('msg', 'Proses cetak SP2HP2 akhir sedang dalam pengerjaan');
    }

    public function bai(Request $request, $kasus_id){
        $dokumen = DokumenPelanggar::where('data_pelanggar_id', $kasus_id)->where('process_id', $request->process_id)->where('sub_process_id', $request->sub_process)->first();
        if($dokumen == null){
            DokumenPelanggar::create([
                'data_pelanggar_id' => $kasus_id,
                'process_id' => $request->process_id,
                'sub_process_id' => $request->sub_process,
                'created_by' => Auth::user()->id,
                'status' => 1
            ]);
        }

        $penyidik1 = Penyidik::where('id', $request->penyidik1)->first();
        $penyidik2 = Penyidik::where('id', $request->penyidik2)->first();
        $dataSaksi = Witness::where('data_pelanggar_id', $kasus_id)->get();
        $sprin = SprinHistory::where('data_pelanggar_id', $kasus_id)->where('type', 'lidik')->first();

        $file = array();
        $kasus = DataPelanggar::find($kasus_id);

        if (count($dataSaksi) == 0){
            for ($i=0; $i < count($request->nama) ; $i++) {
                $template_document = new TemplateProcessor(storage_path('template\template_bai.docx'));
                // Witness::create([
                //     'data_pelanggar_id' => $kasus_id,
                //     'nama' => strtoupper($request->nama[$i]),
                //     'pangkat' => $request->pangkat[$i],
                //     'nrp' => strtoupper($request->nrp[$i]),
                //     'jabatan' => strtoupper($request->jabatan[$i]),
                //     'warga_negara' => $request->warga_negara[$i],
                //     'kesatuan' => $request->kesatuan[$i],
                //     'agama' => $request->agama[$i],
                //     'alamat' => $request->alamat[$i],
                //     'ttl' => $request->ttl[$i],
                //     'no_telp' => $request->no_telp[$i],
                // ]);

                $template_document->setValues(array(
                    'saksi' => strtoupper($request->nama[$i]),
                    'pangkat_saksi' => strtoupper($request->pangkat[$i]),
                    'nrp_saksi' => $request->nrp[$i],
                    'jabatan_saksi' => strtoupper($request->jabatan[$i]),
                    'kesatuan_saksi' => strtoupper($request->kesatuan[$i]),
                    'ttl_saksi' => $request->ttl[$i],
                    'warga_negara_saksi' => strtoupper($request->warga_negara[$i]),
                    'agama_saksi' => $request->agamaText[$i],
                    'alamat_saksi' => $request->alamat[$i],
                    'no_telp_saksi' => $request->no_telp[$i],
                ));

                $template_document->setValues(array(
                    'hari' => Carbon::now()->translatedFormat('l'),
                    'tanggal' => dateToWord(Carbon::now()->translatedFormat('d')),
                    'bulan' => Carbon::now()->translatedFormat('F'),
                    'tahun' => dateToWord(Carbon::now()->translatedFormat('Y')),
                    'tgl' => Carbon::now()->translatedFormat('d-F-Y'),
                    'jam' => date('H:i') . ' WIB',
                    // Data Pemeriksa
                    'pemeriksa1' => strtoupper($penyidik1->name),
                    'pangkat1' => strtoupper($penyidik1->pangkat),
                    'nrp1' => $penyidik1->nrp,
                    'jabatan1' => strtoupper($penyidik1->jabatan),
                    'kesatuan1' => strtoupper($penyidik1->kesatuan),
                    'pemeriksa2' => strtoupper($penyidik2->name),
                    'pangkat2' => strtoupper($penyidik2->pangkat),
                    'nrp2' => $penyidik2->nrp,
                    'jabatan2' => strtoupper($penyidik2->jabatan),
                    'kesatuan2' => strtoupper($penyidik2->kesatuan),
                    // Data Kasus
                    'pangkat' => strtoupper($kasus->pangkat),
                    'terlapor' => strtoupper($kasus->terlapor),
                    'jabatan' => strtoupper($kasus->jabatan),
                    'kesatuan' => strtoupper($kasus->kesatuan),
                    'kronologi' => strtoupper($kasus->kronologi),
                    'no_nd' => strtoupper($kasus->no_nota_dinas),
                    'tgl_nd' => Carbon::parse($kasus->tanggal_nota_dinas),
                    // Data SPRIN
                    'no_sprin' => $sprin->no_sprin,
                    'tgl_sprin' => Carbon::parse($sprin->created_at)->translatedFormat('d-F-Y'),
                ));

                $filename = 'BAI - '.$request->pangkat[$i].' '.$request->nama[$i].'.docx';
                $path = storage_path('document/'.$filename);
                $template_document->saveAs($path);
                array_push($file, $filename);
            }
        } else {
            foreach ($dataSaksi as $saksi) {
                $agama = Agama::where('id', $saksi->agama)->first();
                $template_document = new TemplateProcessor(storage_path('template\template_bai.docx'));
                $template_document->setValues(array(
                    'saksi' => strtoupper($saksi->nama),
                    'pangkat_saksi' => strtoupper($saksi->pangkat),
                    'nrp_saksi' => $saksi->nrp,
                    'jabatan_saksi' => strtoupper($saksi->jabatan),
                    'kesatuan_saksi' => strtoupper($saksi->kesatuan),
                    'ttl_saksi' => $saksi->ttl,
                    'warga_negara_saksi' => strtoupper($saksi->warga_negara),
                    'agama_saksi' => $agama->name,
                    'alamat_saksi' => $saksi->alamat,
                    'no_telp_saksi' => $saksi->no_telp,
                ));

                $template_document->setValues(array(
                    'hari' => Carbon::now()->translatedFormat('l'),
                    'tanggal' => dateToWord(Carbon::now()->translatedFormat('d')),
                    'bulan' => Carbon::now()->translatedFormat('F'),
                    'tahun' => dateToWord(Carbon::now()->translatedFormat('Y')),
                    'tgl' => Carbon::now()->translatedFormat('d-F-Y'),
                    'jam' => date('H:i') . ' WIB',
                    // Data Pemeriksa
                    'pemeriksa1' => strtoupper($penyidik1->name),
                    'pangkat1' => strtoupper($penyidik1->pangkat),
                    'nrp1' => $penyidik1->nrp,
                    'jabatan1' => strtoupper($penyidik1->jabatan),
                    'kesatuan1' => strtoupper($penyidik1->kesatuan),
                    'pemeriksa2' => strtoupper($penyidik2->name),
                    'pangkat2' => strtoupper($penyidik2->pangkat),
                    'nrp2' => $penyidik2->nrp,
                    'jabatan2' => strtoupper($penyidik2->jabatan),
                    'kesatuan2' => strtoupper($penyidik2->kesatuan),
                    // Data Kasus
                    'pangkat' => strtoupper($kasus->pangkat),
                    'terlapor' => strtoupper($kasus->terlapor),
                    'jabatan' => strtoupper($kasus->jabatan),
                    'kesatuan' => strtoupper($kasus->kesatuan),
                    'kronologi' => strtoupper($kasus->kronologi),
                    'no_nd' => strtoupper($kasus->no_nota_dinas),
                    'tgl_nd' => Carbon::parse($kasus->tanggal_nota_dinas)->translatedFormat('d F Y'),
                    // Data SPRIN
                    'no_sprin' => $sprin->no_sprin,
                    'tgl_sprin' => Carbon::parse($sprin->created_at)->translatedFormat('d F Y'),
                ));

                $filename = 'BAI - '.$saksi->pangkat.' '.$saksi->nama.'.docx';
                $path = storage_path('document/'.$filename);
                $template_document->saveAs($path);
                array_push($file, $filename);
            }
        }

        return response()->json(['file' => $file]);
    }

    public function laporanHasilPenyelidikan($kasus_id, $process_id, $subprocess){
        return redirect()->back()->with('msg', 'Proses cetak Laporan Hasil Penyelidikan sedang dalam pengerjaan');

        // $kasus = DataPelanggar::find($kasus_id);
        // $sprin = SprinHistory::where('data_pelanggar_id', $kasus->id)->first();
        // $template_document = new TemplateProcessor(storage_path('template\lhp.docx'));

        // $template_document->setValues(array(
        //     'no_nota_dinas' => $kasus->no_nota_dinas,
        //     'tanggal_nota_dinas' => Carbon::parse($kasus->tanggal_nota_dinas)->translatedFormat('d F Y'),
        //     'pangkat' => $kasus->pangkat,
        //     'jabatan' => $kasus->jabatan,
        //     'kwn' => $kasus->kewarganegaraan,
        //     'terlapor' => $kasus->terlapor,
        //     'wujud_perbuatan' => $kasus->wujud_perbuatan,
        //     'terlapor' => $kasus->terlapor,
        //     'nrp' => $kasus->nrp,
        //     'jabatan' => $kasus->jabatan,
        //     'kesatuan' => $kasus->kesatuan,
        //     'pelapor' => $kasus->pelapor,
        //     'bulan_sprin' => Carbon::parse($sprin->created_at)->translatedFormat('F Y')
        // ));

        // $filename = 'Dokumen LHP'.'.docx';
        // $path = storage_path('document/'.$filename);
        // $template_document->saveAs($path);

        // // $dokumen = DokumenPelanggar::where('data_pelanggar_id', $kasus_id)->where('process_id', $process_id)->where('sub_process_id', $subprocess)->first();
        // // if($dokumen == null){
        // //     DokumenPelanggar::create([
        // //         'data_pelanggar_id' => $kasus_id,
        // //         'process_id' => $process_id,
        // //         'sub_process_id' => $subprocess,
        // //         'created_by' => Auth::user()->id,
        // //         'status' => 1
        // //     ]);
        // // }

        // return response()->download($path)->deleteFileAfterSend(true);
    }

    public function nd_permohonan_gelar_perkara(Request $request, $kasus_id){
        $hari = Carbon::parse($request->tgl)->translatedFormat('l');
        $tgl = Carbon::parse($request->tgl)->translatedFormat('d F Y');

        $dokumen = DokumenPelanggar::where('data_pelanggar_id', $kasus_id)->where('process_id', $request->process_id)->where('sub_process_id', $request->sub_process)->first();
        if($dokumen == null){
            DokumenPelanggar::create([
                'data_pelanggar_id' => $kasus_id,
                'process_id' => $request->process_id,
                'sub_process_id' => $request->sub_process,
                'created_by' => Auth::user()->id,
                'status' => 1
            ]);
        }

        $template_document = new TemplateProcessor(storage_path('template\template_nd_gelar_perkara.docx'));
        $template_document->setValues(array(
            'tgl_ttd' => Carbon::now()->translatedFormat('F Y'),
            'hari' => $hari,
            'tgl' => $tgl,
            'jam' => $request->jam,
            'tempat' => $request->tempat,
            'pimpinan' => $request->pimpinan
        ));

        $filename = 'Dokumen Nota Dinas Permohonan Gelar Perkara'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);
        return response()->json(['file' => $filename]);
    }

    public function undangan_klarifikasi(Request $request, $kasus_id){
        $kasus = DataPelanggar::find($kasus_id);
        $sprin = SprinHistory::where('data_pelanggar_id', $kasus_id)->where('type', 'lidik')->first();
        $penyidik = Penyidik::find($request->penyidik);

        $template_document = new TemplateProcessor(storage_path('template\template_undangan_klarifikasi.docx'));
        $template_document->setValues(array(
            'no' => $request->no_undangan,
            'create_date' => Carbon::now()->translatedFormat('d F Y'),
            'pangkat' => strtoupper($kasus->pangkat),
            'terlapor' => strtoupper($kasus->terlapor),
            'no_nd' => $kasus->no_nota_dinas,
            'tgl_nd' => Carbon::parse($kasus->tanggal_nota_dinas)->translatedFormat('d F Y'),
            'pelapor' => strtoupper($kasus->pelapor),
            'no_sprin' => $sprin->no_sprin,
            'tgl_sprin' => Carbon::parse($sprin->created_at)->translatedFormat('d F Y'),
            'tgl_lapor' => Carbon::parse($kasus->tanggal_kejadian)->translatedFormat('d F Y'), //sementara pakai tanggal kejadian
            'perihal_nd' => $kasus->perihal_nota_dinas,
            'jabatan_terlapor' => strtoupper($kasus->jabatan),
            'kesatuan_terlapor' => strtoupper($kasus->kesatuan),
            'pangkat_penyidik'=> strtoupper($penyidik->pangkat),
            'penyelidik'=> strtoupper($penyidik->name),
            'jabatan_penyelidik'=> strtoupper($penyidik->jabatan),
            'kesatuan_penyelidik'=> strtoupper($penyidik->kesatuan),
            'hari_pertemuan' => Carbon::parse($request->tgl_pertemuan)->translatedFormat('l'),
            'tgl_pertemuan' => Carbon::parse($request->tgl_pertemuan)->translatedFormat('d F Y'),
            'jam_pertemuan' => $request->jam_pertemuan.' WIB'
        ));

        $filename = "Undangan Klarifikasi ".strtoupper($kasus->pangkat)." ".strtoupper($kasus->terlapor).".docx";
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->json(['file' => $filename]);
        // return response()->download($path)->deleteFileAfterSend(true);
    }
    // End of document pulbaket

    // Document Gelar Lidik
    public function sprin_gelar(Request $request, $kasus_id){
        $no_sprin = str_replace('_', '', $request->no_sprin);
        // $sprinHistory = SprinHistory::where('data_pelanggar_id', $kasus_id)->where('type', 'gelar')->first();

        $hari = Carbon::parse($request->tgl /** != ''? $request->tgl : $sprinHistory->tgl_pelaksanaan_gelar */ )->translatedFormat('l');
        $tgl = Carbon::parse($request->tgl /**  != '' ? $request->tgl : $sprinHistory->tgl_pelaksanaan_gelar */ )->translatedFormat('d F Y');
        // if ($sprinHistory == null){
            $sprinHistory = SprinHistory::create([
                'data_pelanggar_id' => $kasus_id,
                'no_sprin' => $no_sprin,
                'created_by' => Auth::user()->id,
                'tgl_pelaksanaan_gelar' => $request->tgl,
                'type' => 'gelar'
            ]);

            $dokumen = DokumenPelanggar::where('data_pelanggar_id', $kasus_id)->where('process_id', $request->process_id)->where('sub_process_id', $request->sub_process)->first();
            if($dokumen == null){
                DokumenPelanggar::create([
                    'data_pelanggar_id' => $kasus_id,
                    'process_id' => $request->process_id,
                    'sub_process_id' => $request->sub_process,
                    'created_by' => Auth::user()->id,
                    'status' => 1
                ]);
            }
        // }

        $template_document = new TemplateProcessor(storage_path('template\template_sprin_gelar_perkara.docx'));
        $template_document->setValues(array(
            'no_sprin' => $no_sprin != '' ? $no_sprin : $sprinHistory->no_sprin,
            'tgl_ttd' => Carbon::now()->translatedFormat('F Y'),
            'hari' => $hari,
            'tgl' => $tgl
        ));

        $filename = 'Dokumen SPRIN Gelar Perkara'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);
        if ($request->method() == 'GET'){
            return response()->download($path)->deleteFileAfterSend(true);
        } else {
            return response()->json(['file' => $filename]);
        }
    }

    public function berkas_undangan_gelar(Request $request, $kasus_id){
        $hari = Carbon::parse($request->tgl)->translatedFormat('l');
        $tgl = Carbon::parse($request->tgl)->translatedFormat('d F Y');

        $dokumen = DokumenPelanggar::where('data_pelanggar_id', $kasus_id)->where('process_id', $request->process_id)->where('sub_process_id', $request->sub_process)->first();
        if($dokumen == null){
            DokumenPelanggar::create([
                'data_pelanggar_id' => $kasus_id,
                'process_id' => $request->process_id,
                'sub_process_id' => $request->sub_process,
                'created_by' => Auth::user()->id,
                'status' => 1
            ]);
        }

        $template_document = new TemplateProcessor(storage_path('template\undangan_gelar.docx'));
        $template_document->setValues(array(
            'tgl_ttd' => Carbon::now()->translatedFormat('F Y'),
            'hari' => $hari,
            'tgl' => $tgl,
            'jam' => $request->jam,
            'tempat' => $request->tempat,
            'pimpinan' => $request->pimpinan
        ));

        $filename = 'Dokumen Undangan Gelar Perkara'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);
        return response()->json(['file' => $filename]);
    }

    public function notulen_hasil_gelar($kasus_id, $process_id, $subprocess){
        return redirect()->back()->with('msg', 'Proses cetak Notulen Hasil Gelar sedang dalam pengerjaan');
    }

    public function laporan_hasil_gelar($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_lap_hasil_gp.docx'));
        $filename = 'Dokumen Laporan Hasil Gelar Perkara'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);
        return response()->download($path)->deleteFileAfterSend(true);
    }
    // End of Document Gelar Lidik

    public function limpah_polda(Request $request){
        (new KasusController())->updateData($request);

        if ($request->next == 'limpah'){
            $template_document = new TemplateProcessor(storage_path('template\template_limpah.docx'));
            $filename = 'Surat Limpah'.'.docx';
            $path = storage_path('document/'.$filename);
            $template_document->saveAs($path);

            return response()->json(['file' => $filename]);
            // return response()->download($path)->deleteFileAfterSend(true);
        }
    }

    // Sidik / LPA
    public function lpa($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_lpa.docx'));
        $filename = 'Surat LPA'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }


    public function sprin_riksa($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_sprin_riksa.docx'));
        $filename = 'SRIN Riksa'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function surat_panggilan_saksi($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_surat_panggilan_saksi.docx'));
        $filename = 'Surat Panggilan Saksi'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function surat_panggilan_terduga($kasus_id, $process_id, $subprocess){
        return redirect()->back()->with('msg', 'Proses cetak Surat Panggilan Terduga sedang dalam pengerjaan');
    }

    public function bap($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_bap.docx'));
        $filename = 'Dokumen BAP'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function dp3d($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_dp3d.docx'));
        $filename = 'Dokumen DP3D'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function pelimpahan_ankum($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_pelimpahan_ankum.docx'));
        $filename = 'Surat Pelimpahan Ke Ankum'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }


    //Sidang Disiplin
    public function nota_dina_perangkat_sidang($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_perangkat_sidang.docx'));
        $filename = 'Nota Dinas Perangkat Sidang'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function sprin_perangkat_sidang($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_perangkat_sidang.docx'));
        $filename = 'SPRIN Perangkat Sidang'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function undangan_sidang_disiplin($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_undangan_sidang.docx'));
        $filename = 'Surat Undangan Sidang Disiplin'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function hasil_putusan_sidang_disiplin($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_hasil_putusan_sidang.docx'));
        $filename = 'Hasil Putusan Sidang Disiplin'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function nota_hasil_putusan($kasus_id, $process_id, $subprocess){
        $template_document = new TemplateProcessor(storage_path('template\template_nota_hasil_putusan.docx'));
        $filename = 'Nota Hasil Putusan Sidang'.'.docx';
        $path = storage_path('document/'.$filename);
        $template_document->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
