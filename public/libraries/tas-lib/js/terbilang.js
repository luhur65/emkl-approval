var terbilang = function (number) {
  let words = [];
  const units = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];

  function terbilangSatuan(n) {
    if (n < 12) {
      return units[n];
    } else if (n < 20) {
      return terbilangSatuan(n - 10) + " Belas";
    } else if (n < 100) {
      return terbilangSatuan(Math.floor(n / 10)) + " Puluh " + terbilangSatuan(n % 10);
    } else if (n < 200) {
      return " Seratus "+ terbilangSatuan(n % 100);
    } else if (n < 1000) {
      return units[Math.floor(n / 100)] + " Ratus " + terbilangSatuan(n % 100);
    }
    // tambahkan kondisi untuk ribu, juta, miliar, dst. sesuai kebutuhan
  }

  function terbilangDesimal(n) {
    n *= 100; // konversi desimal ke integer untuk memudahkan perhitungan
    n = Math.round(n);
    if (n > 0) {
      words.push(terbilangSatuan(n));
    }
  }

  var str = number.toString();
  var desimalIndex = str.indexOf('.');
  var nilaiDesimal = 0;

  if (desimalIndex !== -1) {
    // Jika terdapat desimal
    nilaiDesimal = parseFloat("0." + str.substr(desimalIndex + 1));
    str = str.substr(0, desimalIndex);
  }

  var nilai = parseInt(str);

  if (isNaN(nilai)) {
    return "Input tidak valid";
  }

  if (nilai < 0 || nilai > 999999999999) {
    return "Angka di luar batas";
  }

  if (nilai === 0) {
    words.push("Nol");
  } else {
    if (nilai < 1000) {
      words.push(terbilangSatuan(nilai));
    } else {
      var milyar = Math.floor(nilai / 1000000000);
      var juta = Math.floor((nilai % 1000000000) / 1000000);
      var ribu = Math.floor((nilai % 1000000) / 1000);
      var ratusan = nilai % 1000;

      if (milyar > 0) {
        words.push(terbilangSatuan(milyar) + " Miliar");
      }

      if (juta > 0) {
        words.push(terbilangSatuan(juta) + " Juta");
      }

      if (ribu > 0) {
        words.push(terbilangSatuan(ribu) + " Ribu");
      }

      if (ratusan > 0) {
        words.push(terbilangSatuan(ratusan));
      }
    }
  }

  if (nilaiDesimal > 0) {
    words.push("Koma");
    terbilangDesimal(nilaiDesimal);
  }

  return words.join(" ");
};