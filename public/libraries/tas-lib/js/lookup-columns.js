function lookupConfigList(settings, inputWidth = 0) {
    let selector = $(`#${settings.lookupName}`)
    var label = settings.labelColumn
    width = ''

    //  use this witdh if single column lookup    
    if (detectDeviceType() == "desktop" && label == false) {        
        // width = '1500px'
        width = inputWidth - 2.1
    } else {
        width = selector.parents('.input-group').outerWidth() + 'px'
    }

    var attrColumnMks = false;
    var isReload = 'reload'

    if (settings.lookupKey == 'stokV4') {
        if (accessCabang == 'MAKASSAR') {
            width = '250px'
            attrColumnMks=true
        }    
    }

    let jenisKendaraan = settings.postData.statusjeniskendaraan || '';
    let urlUpahsupir = (jenisKendaraan == 'TANGKI') ? 'upahsupirtangki/get' : 'upahsupirrincian/get';
    
    const global = {
        url: settings.url,
        sortname: settings.sortname,
        column: settings.column,
        filterPostData: settings.postData ? settings.postData : {}
    }
    
    const columns = {
        absensisupirdetailV4: {
            url: `${apiUrl}absensisupirdetail/get`,
            sortname: 'tradosupir',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: 'TRADO - SUPIR',
                    name: 'tradosupir',
                    width: '1500px',
                },
                {
                    label: 'trado',
                    name: 'trado',
                    hidden: true,
                    search: false,
                },
                {
                    label: 'trado_id',
                    name: 'trado_id',
                    hidden: true,
                    search: false,
                },
                {
                    label: 'supir_id',
                    name: 'supir_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'supir',
                    name: 'supir',
                    search: false,
                    hidden: true
                },
                {
                    label: 'statusgerobak',
                    name: 'statusgerobak',
                    hidden: true,
                    search: false
                },
                {
                    label: 'nominalplusborongan',
                    name: 'nominalplusborongan',
                    hidden: true,
                    search: false
                },
                {
                    label: 'UANG JALAN',
                    name: 'uangjalan',
                    hidden: true,
                    search: false
                },
            ],
            filterPostData: {
                aktif: '',
                trado_id: '',
                cabang: '',
                absensiId: '',
                tgltrip: '',
                absensi_id: '',
                from: '',
                aksi: '',
                tripinap_id: '',
                pengajuantrip_id: '',
                isProsesUangjalan: '',
                uangJalanId: '',
                statusjeniskendaraan: '',
                trip_id: '',
                forLookup: true,
            }
        },
        absentradoV4: {
            url: `${apiUrl}absentrado`,
            sortname: 'kodeabsen',
            column: [
                {
                    label: 'ID',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    hidden: true
                },
                {
                    label: 'KODE ABSEN',
                    name: 'kodeabsen',
                    align: 'left',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_1 : sm_mobile_1,
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    align: 'left',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_2 : md_mobile_2,
                },
            ],
            filterPostData: {
                aktif: '',
                aktif: '',
                trado_id: '',
                supir_id: '',
                supirold_id: '',
                tglabsensi: '',
                dari: '',
                equalField: "keterangan",
            }
        },
        agenV4: {
            url: `${apiUrl}customer`,
            sortname: 'namaagen',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "NAMA CUSTOMER",
                    name: "namaagen",
                    width: width,
                },
                {
                    label: 'COA',
                    name: 'coa',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left',
                    search: false,
                    hidden: true
                },
                {
                    label: 'STATUS PEMBAYARAN (TOP)',
                    name: 'top',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_3,
                    align: 'right',
                    formatter: currencyFormat,
                    search: false,
                    hidden: true
                },
                {
                    label: 'biayatambahantrip',
                    name: 'biayatambahantrip',
                    search: false,
                    hidden: true
                },
            ],
            filterPostData: {
                aktif: '',
                invoice: '',
                from: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        akunpusatV4: {
            url: `${apiUrl}akunpusat`,
            sortname: 'coa',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangancoa',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_3 : sm_mobile_4,
                    align: 'left'
                },
                {
                    label: 'COA',
                    name: 'coa',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_2 : sm_mobile_3,
                    align: 'left',
                },
            ],
            filterPostData: {
                levelCoa: '',
                potongan: '',
                aktif: '',
                keterangancoa: '',
                supplier: '',
                coasparepart: false,
                isParent: '',
                manual: '',
                bank: '',
                forHutang: false,
                isLookup: "",
                tipeData: 'JSON',
                equalField: "keterangancoa",
            }
        },
        akuntansiV4: {
            url: `${apiUrl}akuntansi`,
            sortname: "kodeakuntansi",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'KODE AKUNTANSI',
                    name: 'kodeakuntansi',
                    width: width,
                    align: 'left'
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: width,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        alatbayarV4: {
            url: `${apiUrl}alatbayar`,
            sortname: "namaalatbayar",
            column: [
                {
                    label: 'ID',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    hidden: true
                },
                {
                    label: 'NAMA ALAT BAYAR',
                    name: 'namaalatbayar',
                    width: width,
                    align: 'left',
                },
                {
                    label: 'TIPE BANK',
                    name: 'tipebank',
                    hidden: true,
                    search: false,
                }
            ],
            filterPostData: {
                statusdefault: '',
                bank_id: '',
                aktif: '',
                from: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        bankpelangganV4: {
            url: `${apiUrl}bankpelanggan`,
            sortname: 'namabank',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'NAMA BANK',
                    name: 'namabank',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_3,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: "",
                tipeData: 'JSON'
            }
        },
        bankV4: {
            url: `${apiUrl}bank`,
            sortname: "namabank",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "bank",
                    name: "namabank",
                    width: width,
                },
                {
                    label: "tipe",
                    name: "tipe",
                    width: width,
                    hidden: true,
                    search: false,
                }
            ],
            filterPostData: {
                aktif: '',
                filters: '',
                tipe: '',
                bankId: '',
                bankExclude: '',
                alatbayar: '',
                withPusat: '',
                from: '',
                isLookup: "",
                tipeData: 'JSON'
            }
        },
        cabangV4: {
            url: `${apiUrl}cabang`,
            sortname: 'namacabang',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'Nama Cabang',
                    name: 'namacabang',
                    width: width
                },
                {
                    label: 'Kode Cabang',
                    name: 'kodecabang',
                    width: width
                },
            ],
            filterPostData: {
                aktif: '',
                emkl: '',
                transferCoa: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        containerV4: {
            url: `${apiUrl}container`,
            sortname: "kodecontainer",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: "KODE CONTAINER",
                    name: "kodecontainer",
                    width: width,
                },
                {
                    label: "keterangan",
                    name: "keterangan",
                    hidden: true,
                },
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        controllerV4: {
            url: `${apiUrl}menu/controller`,
            sortname: 'class',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'class',
                    name: 'class',
                    align: 'left',
                    width: width
                },  
            ],
            filterPostData: {
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        dataritasiV4: {
            url: `${apiUrl}dataritasi`,
            sortname: "statusritasi",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'STATUS RITASI ID',
                    name: 'statusritasi_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'STATUS RITASI',
                    name: 'statusritasi',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        dettransv4: {
            url: `${apiUrl}dettrans`,
            sortname: "keterangan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "keterangan",
                    name: "keterangan",
                    width: width,
                },
            ],
            filterPostData: {
                aktif: "",
                isLookup: true,
                tipeData: 'JSON',
            }
        },
        emklContainerV4: {
            url: `${apiUrl}tarifemkl/container`,
            sortname: "FKContainer",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "FKContainer",
                    name: 'FKContainer',
                    width: width,
                },
            ],
            filterPostData: {
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        emklHargaTruckingV4: {
            url: `${apiUrl}tarifemkl/hargatrucking`,
            sortname: "FLokasiBongkar",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "FLokasiBongkar",
                    name: 'FLokasiBongkar',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                container_id: settings?.postData?.container_id,
                from: settings?.postData?.from,
                cabangasal: settings?.postData?.cabangasal,
                tujuankapal: settings?.postData?.tujuankapal,
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        emklShipperV4: {
            url: `${apiUrl}tarifemkl/shipper`,
            sortname: "FNShipper",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "FNShipper",
                    name: 'FNShipper',
                    width: width,
                },
            ],
            filterPostData: {
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        emklTujuankapalV4: {
            url: `${apiUrl}tarifemkl/tujuan`,
            sortname: "FNTujuanKapal",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "FNTujuanKapal",
                    name: 'FNTujuanKapal',
                    width: width,
                },
                {
                    label: "FNTujuanKapalCabang",
                    name: 'FNTujuanKapalCabang',
                    width: width,
                    hidden: true,
                },
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        gandenganV4: {
            url: `${apiUrl}gandengan`,
            sortname: "kodegandengan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: "KODE GANDENGAN",
                    name: "kodegandengan",
                    width: '100px',
                },
                {
                    label: "keterangan",
                    name: "keterangan",
                    width: '450px',
                },
            ],
            filterPostData: {
                aktif: '',
                asal: '',
                cabang: '',
                penerimaanstok_id: '',
                gandengandarike: '',
                gandengandari_id: '',
                gandenganke_id: '',
                statusjeniskendaraan: '',
                from: '',
                tgltrip: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        gudangV4: {
            url: `${apiUrl}gudang`,
            sortname: 'gudang',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: "gudang",
                    name: "gudang",
                    width: width,
                },  
            ],
            filterPostData: {
                filters: '',
                aktif: '',
                penerimaanstok_id: '',
                pengeluaranstok_id: '',
                gudangdarike: '',
                gudangdari_id: '',
                gudangke_id: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        jenisbiayaV4: {
            url: `${apiUrl}jenisbiaya`,
            sortname: 'keterangan',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: "KETERANGAN",
                    name: "keterangan",
                    width: width,
                }
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        jenisfakturV4: {
            url: `${apiUrl}jenisfaktur`,
            sortname: "keterangan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "keterangan",
                    name: "keterangan",
                    width: width,
                },
                {
                    label: "keterangan2",
                    name: "keterangan2",
                    width: width,
                },
            ],
            filterPostData: {
                aktif: "",
                isLookup: true,
                tipeData: 'JSON',
            }
        },
        jenisorderV4: {
            url: `${apiUrl}jenisorder`,
            sortname: "keterangan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: "KETERANGAN",
                    name: "keterangan",
                    width: width,
                }
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        jenistradoV4: {
            url: `${apiUrl}jenistrado`,
            sortname: "kodejenistrado",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "KODE jenistrado",
                    name: 'kodejenistrado',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        jobtruckingV4: {
            url: `${apiUrl}jobtrucking`,
            sortname: 'jobtrucking',
            column: [
                {
                    label: 'JOB TRUCKING',
                    name: 'jobtrucking',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    // hidden: true,
                    // sortable: false,
                    // search: false,
                },
                // {
                //     label: 'JOB TRUCKING',
                //     name: 'jobtrip',
                //     width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                // },
                {
                    label: 'TGL BUKTI',
                    name: 'tglbukti',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_2 : sm_mobile_2,
                    align: 'right',
                    formatter: "date",
                    formatoptions: {
                        srcformat: "ISO8601Long",
                        newformat: "d-m-Y"
                    }
                },
                {
                    label: 'SUPIR',
                    name: 'supir',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                },
                {
                    label: 'TRADO',
                    name: 'trado',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'DARI',
                    name: 'kotadari',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'SAMPAI',
                    name: 'kotasampai',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'NOBUKTI',
                    name: 'nobukti',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                // {
                //     label: 'RITASI',
                //     name: 'kotadarisampai',
                //     width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                // },
            ],
            filterPostData: {
                edit: 'false',
                idtrip: '',
                statuscontainer_id: '',
                container_id: '',
                jenisorder_id: '',
                pelanggan_id: '',
                gandengan_id: '',
                trado_id: '',
                tarif_id: '',
                statuslongtrip: '',
                tripasal: '',
                isPulangLongtrip: '',
                tglbukti: '',
                dari_id: '',
                filters: '',
                forLookup: true,
            }
        },
        karyawanhrV4: {
            url: `${apiUrl}user/karyawanhr`,
            sortname: "namakaryawan",
            column: [
                {
                    label: 'karyawanid',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    sortable: false,
                    hidden: true,
                },
                {
                    label: 'Nama Karyawan',
                    name: 'namakaryawan',
                    align: 'left',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                cabang: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        karyawanV4: {
            url: `${apiUrl}karyawan`,
            sortname: "namakaryawan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'NAMA KARYAWAN',
                    name: 'namakaryawan',
                    align: 'left',
                    width: width
                },
            ],
            filterPostData: {
                aktif: '',
                staff: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        kategoriV4: {
            url: `${apiUrl}kategori`,
            sortname: "kodekategori",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "kode kategori",
                    name: "kodekategori",
                    width: width,
                },
                {
                    label: 'Keterangan',
                    name: 'keterangan',
                    width: width,
                },
            ],
            filterPostData:{
                aktif: '',
                subkelompok: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        kelompokV4: {
            url: `${apiUrl}kelompok`,
            sortname: 'kodekelompok',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "KODE kelompok",
                    name: "kodekelompok",
                    width: width,
                },
                {
                    label: 'Keterangan',
                    name: 'keterangan',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        kerusakanV4: {
            url: `${apiUrl}kerusakan`,
            sortname: "keterangan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'Keterangan',
                    name: 'keterangan',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        kotaV4: {
            url: `${apiUrl}kota`,
            sortname: "kodekota",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'KOTA',
                    name: 'kodekota',
                    align: 'left',
                    width: width,
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    align: 'left',
                    hidden: true,
                    sortable: false,
                    search: false,
                },
            ],
            filterPostData: {
                aktif: '',
                statuspelabuhan: '',
                kotadari_id: '',
                kotasampai_id: '',
                pilihkota_id: '',
                dataritasi_id: '',
                ritasidarike: '',
                zonadari_id: '',
                zonasampai_id: '',
                upahSupirDariKe: '',
                upahSupirKotaDari: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        kotazonaV4: {
            url: `${apiUrl}kota`,
            sortname: "kodekota",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: 'KOTA',
                    name: 'kodekota',
                    align: 'left',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                kotaZona: '',
                isLookup: '',
                statuslongtrip: '',
                dari_id: '',
                from: '',
                forLookup: true,
                tipeData: 'JSON'
            }
        },
        mainakunpusatV4: {
            url: `${apiUrl}mainakunpusat`,
            sortname: "keterangancoa",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'KODE PERKIRAAN',
                    name: 'coa',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    align: 'left'
                },
                {
                    label: 'NAMA',
                    name: 'keterangancoa',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                level: '',
                potongan: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        maintypeakuntansiV4: {
            url: `${apiUrl}maintypeakuntansi`,
            sortname: "kodetype",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'KODE TIPE',
                    name: 'kodetype',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    align: 'left'
                },
                {
                    label: 'AKUNTANSI',
                    name: 'akuntansi',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        mandorV4: {
            url: `${apiUrl}mandor`,
            sortname: "namamandor",
            column: [
                {
                    label: 'ID',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    hidden: true
                },
                {
                    label: 'NAMA mandor',
                    name: 'namamandor',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    align: 'left'
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: (detectDeviceType() == "desktop") ? lg_dekstop_1 : lg_mobile_2,
                    align: 'left'
                },
                {
                    label: 'STATUS AKTIF',
                    name: 'statusaktif',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    formatter: (value, options, rowData) => {
                    let statusAktif = JSON.parse(value)

                    let formattedValue = $(`
                        <div class="badge" style="background-color: ${statusAktif.WARNA}; color: ${statusAktif.WARNATULISAN};">
                            <span>${statusAktif.SINGKATAN}</span>
                        </div>
                        `)

                    return formattedValue[0].outerHTML
                    },
                    cellattr: (rowId, value, rowObject) => {
                    let statusAktif = JSON.parse(rowObject.statusaktif)

                    return ` title="${statusAktif.MEMO}"`
                    }
                },
                {
                    label: 'MODIFIED BY',
                    name: 'modifiedby',
                    search: false,
                    hidden: true,
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left'
                },
                {
                    label: 'CREATED AT',
                    name: 'created_at',
                    align: 'right',
                    search: false,
                    hidden: true,
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    formatter: "date",
                    formatoptions: {
                    srcformat: "ISO8601Long",
                    newformat: "d-m-Y H:i:s"
                    }
                },
                {
                    label: 'UPDATED AT',
                    name: 'updated_at',
                    align: 'right',
                    search: false,
                    hidden: true,
                    formatter: "date",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    formatoptions: {
                    srcformat: "ISO8601Long",
                    newformat: "d-m-Y H:i:s"
                    }
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        marketingV4: {
            url: `${apiUrl}marketing`,
            sortname: "kodemarketing",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'marketing',
                    name: 'kodemarketing',
                    align: 'left',
                    width: '1500px'
                }
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        menuparentV4: {
            url: `${apiUrl}menu/combomenuparent`,
            sortname: 'menuparent',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'MENU PARENT',
                    name: 'menuparent',
                    align: 'left',
                    width: width
                },  
                {
                    label: 'PARAM',
                    name: 'param',
                    align: 'left',
                    width: width,
                    hidden: true,
                    sortable: false,
                    search: false
                },  
            ],
            filterPostData: {
                status: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        merkV4: {
            url: `${apiUrl}merk`,
            sortname: "kodemerk",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "KODE merk",
                    name: "kodemerk",
                    width: width,
                },
                {
                    label: 'Keterangan',
                    name: 'keterangan',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        mingguanV4: {
            url: `${apiUrl}laporanarusdanapusat/mingguan`,
            sortname: "id",
            column: [
                {
                    label: 'Minggu Ke',
                    name: 'fMingguKe',
                    align: 'left',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_1 : sm_mobile_1,
                },
                
                {
                    label: 'Tgl Dari',
                    name: 'fTglDr',
                    align: 'left',
                    formatter: "date",
                    formatoptions: {
                        srcformat: "ISO8601Long",
                        newformat: "d-m-Y"
                    },
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    
                },
                {
                    label: 'Tgl Sampai',
                    name: 'fTglSd',
                    align: 'left',
                    formatter: "date",
                    formatoptions: {
                        srcformat: "ISO8601Long",
                        newformat: "d-m-Y"
                    },
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'Minggu',
                    name: 'fKode',
                    align: 'left',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_2 : md_mobile_2,
                },
                {
                    label: 'Tahun',
                    name: 'fTahun',
                    align: 'left',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'Bulan Ke',
                    name: 'fBulanKe',
                    align: 'left',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
            ],
            filterPostData: {
                aktif: '',
                bulan: settings.postData.bulan ? `01-${settings.postData.bulan}` : '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        parameterAllV4: {
            url: `${apiUrl}parameter`,
            sortname: 'grp',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'GROUP',
                    name: 'grp',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },
                {
                    label: 'SUB GROUP',
                    name: 'subgrp',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },
                {
                    label: 'KELOMPOK',
                    name: 'kelompok', 
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },
                {
                    label: 'TEXT',
                    name: 'text',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },   
            ],
            filterPostData: {
                grp: '',
                subgrp: '',
                filters: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        parameterMemoV4: {
            url: `${apiUrl}parameter`,
            sortname: "textmemo",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: 'TEXT',
                    name: 'textmemo',
                    width: width,
                },
                {
                    label: "memo",
                    name: "memo",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
            ],
            filterPostData: {
                grp: '',
                subgrp: '',
                filters: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        parameterV4: {
            url: `${apiUrl}parameter`,
            sortname: "text",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: 'TEXT',
                    name: 'text',
                    width: width,
                },
                {
                    label: "memo",
                    name: "memo",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
            ],
            filterPostData: {
                grp: '',
                subgrp: '',
                filters: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        pelangganV4: {
            url: `${apiUrl}shipper`,
            sortname: "kodepelanggan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                
                {
                    label: "KODE PENERIMA BARANG",
                    name: "kodepelanggan",
                    width: width,
                },
                {
                    label: "nama PENERIMA BARANG",
                    name: "namapelanggan",
                    hidden: true,
                },
            ],
            filterPostData: {
                aktif: '',
                jenisorder_id: '',
                fromInput: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        penerimaanstokV4: {
            url: `${apiUrl}penerimaanstok`,
            sortname: "kodepenerimaan",
            column: [
                {
                    label: 'ID',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    hidden: true
                },
                {
                    label: 'PENERIMAAN',
                    name: 'kodepenerimaan',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left',
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                // roleInput: '',
                roleinput: '',
                isLookup: '',
                forLookup: true,
                isLookup2: true,
                tipeData: 'JSON'
            }
        },
        penerimaantruckingV4: {
            url: `${apiUrl}penerimaantrucking`,
            sortname: "kodepenerimaan",
            column: [
                {
                    label: 'ID',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    hidden: true
                },
                {
                    label: 'pengeluaran',
                    name: 'kodepenerimaan',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left',
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                roleinput: '',
                isLookup: '',
                tipeData: 'JSON',
                equalField: "keterangan",
            }
        },
        pengeluaranstokV4: {
            url: `${apiUrl}pengeluaranstok`,
            sortname: "kodepengeluaran",
            column: [
                {
                    label: 'ID',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    hidden: true
                },
                {
                    label: 'pengeluaran',
                    name: 'kodepengeluaran',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left',
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                // roleInput: '',
                roleinput: '',
                isLookup: '',
                forLookup: true,
                isLookup2: true,
                tipeData: 'JSON'
            }
        },
        pengeluarantruckingV4: {
            url: `${apiUrl}pengeluarantrucking`,
            sortname: "kodepengeluaran",
            column: [
                {
                    label: 'ID',
                    name: 'id',
                    align: 'right',
                    width: '70px',
                    search: false,
                    hidden: true
                },
                {
                    label: 'pengeluaran',
                    name: 'kodepengeluaran',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left',
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                roleinput: '',
                isLookup: '',
                tipeData: 'JSON',
                equalField: "keterangan",
            }
        },
        satuanV4: {
            url: `${apiUrl}satuan`,
            sortname: "satuan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "satuan",
                    name: 'satuan',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        shipperEmklV4: {
            url: `${apiUrl}shipperemkl`,
            sortname: "shipper",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "NAMA SHIPPER",
                    name: "shipper",
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        shipperPenyesuaianV4: {
            url: `${apiUrl}shipperpenyesuaian`,
            sortname: 'penyesuaian',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "SHIPPER PENYESUAIAN",
                    name: "penyesuaian",
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        statuscontainerV4: {
            url: `${apiUrl}statuscontainer`,
            sortname: "kodestatuscontainer",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: "KODE STATUS CONTAINER",
                    name: "kodestatuscontainer",
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        stokV4: {
            url: `${apiUrl}stok`,
            sortname: "namastok",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'NAMA',
                    name: 'namastok',
                    align: 'left',
                    search: true,
                    width: width
                },
                {
                    name: 'iddetail',
                    search: false,
                    hidden: true
                },
                
                {
                    name: 'keterangan',
                    width:width,
                    search: (attrColumnMks),
                    hidden: (!attrColumnMks)
                },
                {
                    name: 'namaterpusat',
                    search: false,
                    hidden: true
                },
                {
                    name: 'kelompok',
                    search: false,
                    hidden: true
                },
                {
                    name: 'satuan',
                    search: false,
                    hidden: true
                },
                {
                    name: 'statusban',
                    search: false,
                    hidden: true
                },
                {
                    name: 'jenistrado',
                    search: false,
                    hidden: true
                },
                {
                    name: 'subkelompok',
                    search: false,
                    hidden: true
                },
                {
                    name: 'kategori',
                    search: false,
                    hidden: true
                },
                {
                    name: 'merk',
                    search: false,
                    hidden: true
                },
                {
                    name: 'qtymin',
                    search: false,
                    hidden: true
                },
                {
                    name: 'qtymax',
                    search: false,
                    hidden: true
                },
                {
                    name: 'vulkan',
                    search: false,
                    hidden: true
                },
                {
                    name: 'kelompok_id',
                    search: false,
                    hidden: true
                },
                {
                    name: 'statusban_id',
                    search: false,
                    hidden: true
                },
                {
                    name: 'vulkanplus',
                    search: false,
                    hidden: true
                },
                {
                    name: 'vulkanminus',
                    search: false,
                    hidden: true
                },
                {
                    name: 'penerimaanstokdetail_keterangan',
                    search: false,
                    hidden: true
                },
                {
                    name: 'penerimaanstokdetail_qty',
                    search: false,
                    hidden: true
                },
                {
                    name: 'penerimaanstokdetail_harga',
                    search: false,
                    hidden: true
                },
                {
                    name: 'penerimaanstokdetail_total',
                    search: false,
                    hidden: true
                },
                {
                    name: 'statusservicerutin',
                    search: false,
                    hidden: true
                },
                {
                    name: 'servicerutin_text',
                    search: false,
                    hidden: true
                },
            ],
            filterPostData: {
                statusdefault: '',
                aktif: '',
                isLookup: true,
                proses: (settings.postData.isReload != 'page') ? isReload : settings.postData.isReload,
                // proses: isReload,
                statusreuse: '',
                approveReuse: '',
                penerimaanstok_id: '',
                pengeluaranstok_id: '',
                penerimaanstokheader_nobukti: '',
                from: '',
                nobukti: '',
                KelompokId: '',
                StokId: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        subkelompokV4: {
            url: `${apiUrl}subkelompok`,
            sortname: "kodesubkelompok",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'Sub kelompok',
                    name: 'kodesubkelompok',
                    width: width,
                },
                {
                    label: 'Keterangan',
                    name: 'keterangan',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                kelompok: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        supirV4: {
            url: `${apiUrl}supir`,
            sortname: "namasupir",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'NAMA',
                    name: 'namasupir',
                    align: 'left',
                    width: '350px'
                },
                {
                    label: 'NAMA Alias',
                    name: 'namaalias',
                    align: 'left',
                    width: '350px'
                },
            ],
            filterPostData: {
                aktif: '',
                absen: '',
                supir_id: '',
                tgltrip: '',
                fromSupirSerap: '',
                trado_id: '',
                from: '',
                fromLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        supplierV4: {
            url: `${apiUrl}supplier`,
            sortname: "namasupplier",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "supplier",
                    name: 'namasupplier',
                    width: width,
                },
            ],
            filterPostData: {
                aktif: '',
                from: '',
                forLookup: true,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        suratpengantartripinapV4: {
            url: `${apiUrl}suratpengantar/gettripinap`,
            sortname: 'nobukti',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                }, 
                {
                    label: 'NO BUKTI',
                    name: 'nobukti'
                },
                {
                    label: 'TGL BUKTI',
                    name: 'tglbukti',
                    formatter: "date",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_2 : sm_mobile_2,
                    formatoptions: {
                        srcformat: "ISO8601Long",
                        newformat: "d-m-Y"
                    }
                },
                {
                    label: 'FULL/EMPTY',
                    name: 'statuscontainer_id',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4
                },
            ],
            filterPostData: {
                aktif: '',
                tglabsensi: '',
                trado_id: '',
                supir_id: '',
                from: '',
            }
        },
        tariftangkiV4: {
            url: `${apiUrl}tariftangki`,
            sortname: "tujuan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'tujuan',
                    name: 'tujuan',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'penyesuaian',
                    name: 'penyesuaian',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'kota',
                    name: 'kota_id',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },
                {
                    label: 'kotaid',
                    name: 'kotaId',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                    hidden: true,
                },
                {
                    label: "Modified By",
                    name: "modifiedby",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    hidden: true,
                },
                {
                    label: "Created At",
                    name: "created_at",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    formatter: "date",
                    formatoptions: {
                    srcformat: "ISO8601Long",
                    newformat: "d-m-Y H:i:s",
                    },
                    align: 'right',
                    hidden: true,
                },
                {
                    label: "Updated At",
                    name: "updated_at",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    formatter: "date",
                    formatoptions: {
                    srcformat: "ISO8601Long",
                    newformat: "d-m-Y H:i:s",
                    },
                    align: 'right',
                    hidden: true,
                },
                {
                    label: "Tujuan-Penyesuaian",
                    name: "tujuanpenyesuaian",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    hidden: true,
                },
            ],
            filterPostData: {
                aktif: '',
                isParent: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        tarifV4: {
            url: `${apiUrl}tarif`,
            sortname: "tujuan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'UPAH SUPIR',
                    name: 'upahsupir',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },
                {
                    label: 'TUJUAN',
                    name: 'tujuan',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },
                {
                    label: 'PENYESUAIAN',
                    name: 'penyesuaian',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,

                },
                {
                    label: 'tujuanpenyesuaian',
                    name: 'tujuanpenyesuaian',
                    hidden: true,
                },
                {
                    label: 'KOTAID',
                    name: 'kotaId',
                    hidden: true,
                    search: false,
                },
                {
                    label: 'Penyesuaian ID',
                    name: 'shipperpenyesuaian_id',
                    hidden: true,
                    search: false,
                },
            ],
            filterPostData: {
                aktif: '',
                jenisOrder: '',
                isParent: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        tradoV4: {
            url: `${apiUrl}trado`,
            sortname: "kodetrado",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    search: false,
                },
                {
                    label: 'NO POLISI',
                    name: 'kodetrado',
                    width:'150px'
                },
                {
                    label: 'status',
                    name: 'statusaktif',
                    width:'70px',

                    formatter: (value, options, rowData) => {
                        let statusAktif = JSON.parse(value)

                        let formattedValue = $(`
                        <div class="badge" style="background-color: ${statusAktif.WARNA}; color: ${statusAktif.WARNATULISAN};">
                            <span>${statusAktif.SINGKATAN}</span>
                        </div>
                        `)
                        return formattedValue[0].outerHTML

                    },
                    cellattr: (rowId, value, rowObject) => {
                        let statusAktif = JSON.parse(rowObject.statusaktif)

                        return ` title="${statusAktif.MEMO}"`
                    }
                },
                {
                    label: 'KM GANTI OLI AKHIR',
                    name: 'kmakhirgantioli',
                    align: 'right',
                    search: false,
                    hidden: true,
                    formatter: currencyFormat,
                },
                {
                    label: 'MEREK',
                    name: 'merek',
                    search: false,
                    hidden: true,
                },
                {
                    label: 'NO RANGKA',
                    name: 'norangka',
                    search: false,
                    hidden: true,
                },
                {
                    label: 'NO MESIN',
                    name: 'nomesin',
                    search: false,
                    hidden: true,
                },
                {
                    label: 'NO STNK',
                    name: 'nostnk',
                    search: false,
                    hidden: true,
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    hidden: false,
                    search: true,
                },
                {
                    label: 'supir',
                    name: 'supir_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'supirid',
                    name: 'supirid',
                    search: false,
                    hidden: true
                },
            ],
            filterPostData: {
                aktif: '',
                trado_id: '' ,
                cabang: '' ,
                penerimaanstok_id: '' ,
                supirserap: '' ,
                tglabsensi: '' ,
                tradodarike: '' ,
                tradodari_id: '' ,
                tradoke_id: '' ,
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        triptangkiV4: {
            url: `${apiUrl}triptangki`,
            sortname: 'keterangan',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: (detectDeviceType() == "desktop") ? lg_dekstop_1 : lg_mobile_2,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                tglbukti: '',
                statusjeniskendaraan: '',
                trado_id: '',
                supir_id: '',
                from: '',
                lookup: true,
                forLookup: true,
            }
        },
        tujuanV4: {
            url: `${apiUrl}tujuan`,
            sortname: "kodetujuan",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'TUJUAN',
                    name: 'kodetujuan',
                    width: width
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                    width: "250px"
                }
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON',
                equalField: "keterangan"
            }
        },
        typeakuntansiV4: {
            url: `${apiUrl}typeakuntansi`,
            sortname: "kodetype",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'KODE TIPE',
                    name: 'kodetype',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    align: 'left'
                },
                {
                    label: 'AKUNTANSI',
                    name: 'akuntansi',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                    align: 'left'
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        upahsupirrincianV4: {
            url: `${apiUrl}${urlUpahsupir}`,
            sortname: 'id',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },

                {
                    label: 'RITASI',
                    name: 'kotadarisampai',
                    width: (detectDeviceType() == "desktop") ? lg_dekstop_2 : lg_mobile_2,
                    align: 'left'
                },
                {
                    label: 'Container',
                    name: 'container',
                    // search: false,
                    // hidden: true
                },
                {
                    label: 'Container ID',
                    name: 'container_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'Omset',
                    name: 'omset',
                    align: 'right',
                    formatter: currencyFormat,
                },
                {
                    label: 'Nominal Supir',
                    name: 'nominalsupir',
                    align: 'right',
                    formatter: currencyFormat,
                },
                {
                    label: 'upah id',
                    name: 'upah_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'Kota dari Id',
                    name: 'kotadari_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'Kota Sampai Id',
                    name: 'kotasampai_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'Zona dari Id',
                    name: 'zonadari_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'Zona Sampai Id',
                    name: 'zonasampai_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'Tarif ID',
                    name: 'tarif_id',
                    search: false,
                    hidden: true
                },
                {
                    label: 'Tarif',
                    name: 'tarif',
                    align: 'left',
                    search: false,
                    hidden: true
                },
                {
                    label: 'DARI',
                    name: 'kotadari',
                    align: 'left',
                    search: false,
                    hidden: true
                },
                {
                    label: 'TUJUAN',
                    name: 'kotasampai',
                    align: 'left',
                    search: false,
                    hidden: true
                },
                {
                    label: 'PENYESUAIAN',
                    name: 'penyesuaian',
                    align: 'left',
                    search: false,
                    hidden: true
                },
                {
                    label: 'nominalkenek',
                    name: 'nominalkenek',
                    align: 'left',
                    search: false,
                    hidden: true
                },
                {
                    label: 'nominalkomisi',
                    name: 'nominalkomisi',
                    align: 'left',
                    search: false,
                    hidden: true
                },
            ],
            filterPostData: {
                aktif: '',
                container_id: settings.postData.container_id ?? '',
                statuscontainer_id: '',
                shipperpenyesuaian: '',
                jenisorder_id: '',
                statuskandang_id: '',
                statusupahzona: '',
                tglbukti: '',
                longtrip: '',
                bongkarmuat2x: '',
                batalmuat: '',
                dari_id: '',
                sampai_id: '',
                statuspenyesuaian: '',
                statusperalihan: '',
                statuslangsir: '',
                statusrepo: '',
                nobukti_tripasal: '',
                shipperemkl_id: 0,
                forLookup: true,
                equalField: "kotadarisampai",
            }
        },
        upahsupirtangkiV4: {
            url: `${apiUrl}upahsupirtangki`,
            sortname: "kotadari_id",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'dari',
                    name: 'kotadari_id',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'tujuan',
                    name: 'kotasampai_id',
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: 'penyesuaian',
                    name: 'penyesuaian',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                },
                {
                    label: 'jarak',
                    name: 'jarak',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                    align: 'right',
                },
                {
                    label: 'tgl mulai berlaku',
                    name: 'tglmulaiberlaku',
                    width: (detectDeviceType() == "desktop") ? md_dekstop_1 : md_mobile_1,
                    formatter: "date",
                    formatoptions: {
                    srcformat: "ISO8601Long",
                    newformat: "d-m-Y"
                    }
                },
                {
                    label: "Modified By",
                    name: "modifiedby",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_3 : sm_mobile_3,
                },
                {
                    label: "Created At",
                    name: "created_at",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    formatter: "date",
                    formatoptions: {
                        srcformat: "ISO8601Long",
                        newformat: "d-m-Y H:i:s",
                    },
                    align: 'right',
                },
                {
                    label: "Updated At",
                    name: "updated_at",
                    width: (detectDeviceType() == "desktop") ? sm_dekstop_4 : sm_mobile_4,
                    formatter: "date",
                    formatoptions: {
                        srcformat: "ISO8601Long",
                        newformat: "d-m-Y H:i:s",
                    },
                    align: 'right',
                },
            ],
            filterPostData: {
                aktif: '',
                isParent: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        upahsupirV4: {
            url: `${apiUrl}upahsupir`,
            sortname: "kotasampai_id",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    align: 'right',        
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'TUJUAN',
                    name: 'kotasampai_id',
                    align: 'left'          
                },     
                {
                    label: 'TUJUANid',
                    name: 'kotasampaiid',
                    hidden:true,
                    align: 'left'          
                },     
                {
                    label: 'DARI',
                    name: 'kotadari_id',
                    align: 'left'          
                },
                {
                    label: 'PENYESUAIAN',
                    name: 'penyesuaian',
                    align: 'left'
                },
                {
                    label: 'JARAK',
                    name: 'jarak',
                    align: 'right',
                },
                {
                    label: 'ZONA',
                    name: 'zona_id',
                    align: 'left'
                },
                {
                    label: 'shipperpenyesuaianid',
                    name: 'shipperpenyesuaian_id',
                    hidden: true,
                    search: false
                },     
            ],
            filterPostData: {
                aktif: '',
                jenisOrder: '',
                isParent: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        userV4: {
            url: `${apiUrl}user`,
            sortname: 'user',
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'user',
                    name: 'user',
                    align: 'left',
                    width: width
                },  
            ],
            filterPostData: {
                role: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        zonaV4: {
            url: `${apiUrl}zona`,
            sortname: "zona",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: 'ZONA',
                    name: 'zona',
                },
                {
                    label: 'KETERANGAN',
                    name: 'keterangan',
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        },
        InitialV4: {

            url: `${apiUrl}initial`,
            sortname: "kodeinitial",
            column: [
                {
                    label: "ID",
                    name: "id",
                    width: "50px",
                    hidden: true,
                    sortable: false,
                    search: false,
                },
                {
                    label: "KODE INITIAL",
                    name: "kodeinitial",
                },
            ],
            filterPostData: {
                aktif: '',
                isLookup: true,
                tipeData: 'JSON'
            }
        }
    }

    if (!settings.lookupKey) {
        return global
    } else {
        if (!columns[settings.lookupKey]) {
            console.error(`Lookup column untuk "${settings.lookupKey}" tidak ditemukan`);
            return null;
        }
        return columns[settings.lookupKey];
    }
};