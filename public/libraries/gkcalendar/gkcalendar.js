var today = new Date();
var date = new Date();
var month = date.getMonth();
var year = date.getFullYear();
var months = new Array("January","February","March","April","May","June","July","August","September","October","November","December");
var data = {};
var dataTgl=[];
var checkBox = $("<input type='checkbox'/>");
var checked1 = false;
var tlhpilih = '';
var jlhpilih = 0;
var isdisabled = false;
var a = document.getElementById('gkcalendar');
frm = document.createElement('FORM');
frm.method = "POST";
frm.action = "#";
frm.id = 'theForm';
tbl = document.createElement('TABLE');
tbl.className = 'tablecalendar';
tbl.id = 'calendar';
tbl.cellSpacing = '2';
tbl.cellPadding = '0';
var tableBody = document.createElement('TBODY');
tbl.appendChild(tableBody);
var tr = document.createElement('TR');
tr.className = 'top';
tableBody.appendChild(tr);
for (var j = 0; j < 8; j++) {
    var td = document.createElement('TD');
    td.className = 'tdcalendar';
    if (j == 0) {
        var btn = document.createElement('INPUT');
        btn.type = 'BUTTON';
        btn.value = '<';
        btn.id = 'prevMonth';
        btn.className = 'button';
        td.appendChild(btn);
        tr.appendChild(td)
    } else if (j == 1) {
        td.colSpan = '2';
        td.id = 'month';
        td.appendChild(document.createTextNode('\u00a0'));
        tr.appendChild(td)
    } else if (j == 3) {
        var btn = document.createElement('INPUT');
        btn.type = 'BUTTON';
        btn.value = '>';
        btn.id = 'nextMonth';
        btn.className = 'button';
        td.appendChild(btn);
        tr.appendChild(td)
    } else if (j == 4) {
        var btn = document.createElement('INPUT');
        btn.type = 'BUTTON';
        btn.value = 'Select All';
        btn.id = 'selectAll';
        btn.className = 'wideButton';
        td.colSpan = '2';
        td.appendChild(btn);
        tr.appendChild(td)
    } else if (j == 6) {
        var btn = document.createElement('INPUT');
        btn.type = 'BUTTON';
        btn.value = 'Done';
        btn.id = 'done';
        btn.style.display='none';
        btn.className = 'wideButton';
        td.colSpan = '2';
        td.appendChild(btn);
        tr.appendChild(td)
    }
}
var tr = document.createElement('TR');
tr.className = 'leader';
tableBody.appendChild(tr);
var minggu = ['\u00a0', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
for (var m = 0; m < 8; m++) {
    var td = document.createElement('TD');
    td.className = 'tdcalendar';
    td.appendChild(document.createTextNode(minggu[m]));
    tr.appendChild(td)
}
for (var i = 0; i < 6; i++) {
    var tr = document.createElement('TR');
    tr.className = 'days';
    tableBody.appendChild(tr);
    for (var j = 0; j < 8; j++) {
        if (j == 0) {
            tr.id = 'test'
        }
        var td = document.createElement('TD');
        if (j == 0) {
            td.className = 'leader';
            var btn = document.createElement('INPUT');
            btn.type = 'BUTTON';
            btn.value = '>>';
            btn.className = 'selectRow button';
            td.appendChild(btn)
        } else {
            td.className = 'day';
            td.appendChild(document.createTextNode('\u00a0'))
        }
        tr.appendChild(td)
    }
}
var tr = document.createElement('TR');
tableBody.appendChild(tr);
var td = document.createElement('TD');
td.className = 'leader';
td.colSpan = '8';
td.appendChild(document.createTextNode('\u00a0'));
tr.appendChild(td);
a.appendChild(frm);
a.appendChild(tbl);
$("#selectAll").click(function() {
    $("#calendar input:checkbox").each(function() {
        if (checked1 == this.checked)
            this.click()
    });
    checked1 = !checked1;
    return false
});

$("#calendar .selectRow").click(function(A) {
    var B = !$(A.target).parent().parent().find("input:checkbox")[0].checked;
    $(A.target).parent().parent().find("input:checkbox").each(function() {
        if (this.checked != B)
            this.click()
    });
    return false
});
$("#prevMonth").click(function() {
    var now = new Date();
    if((date.getMonth()-1)<now.getMonth()){
        if(date.getYear()==now.getYear){
            $(this).attr('disabled','');
            return false;            
        }

    }else{
        $(this).removeAttr('disabled','');
    }    
    date.setMonth(date.getMonth() - 1);
    
    fillCalendar("<?php echo @$row['tglcuti'];?>", isdisabled)
});
$("#nextMonth").click(function() {
    $("#prevMonth").removeAttr('disabled');
    date.setMonth(date.getMonth() + 1);
    fillCalendar("<?php echo @$row['tglcuti'];?>", isdisabled)
});
function custom_sort(a, b) {
    return new Date(a.lastUpdated).getTime() - new Date(b.lastUpdated).getTime();
}
$("#done").click(function() {
    var A = $("#theForm");
    var B = 0;
    var C = '';
    $.each(data, function(key, val) {
        if (key.trim() != '' && /mon|tue|wed|thu|fri|sat/g.test(key.toLowerCase())) {
            C = key + ',' + C;
            B++;
        }
    });
    tlhpilih = C.substr(0, C.length - 1);
    jlhpilih = B;
    tlhpilih = tlhpilih.split(",");
    tlhpilih.sort((a,b)=> new Date(a)-new Date(b));
    var lampau = tlhpilih.filter(function (item) {
      return new Date(item)<new Date();
    });
    $("#jlhlampau").val(lampau.length);
    tlhpilih = tlhpilih.toString()
    $('#selecteddays').val(tlhpilih);
    $('#totaldays').val(jlhpilih);
});
date.setDate(1);
let tglLewat;
function fillCalendar(A, B,isOld=true) {
    tglLewat = tglLewat==undefined?isOld:tglLewat;
    if (typeof A !== 'undefined') {
        var C = A.split(',');
        var D = '';
        var E;
        var F;
        for (F = 0; F < C.length; F++) {
            data[C[F]] = true
        }
    }
    month = date.getMonth();
    year = date.getFullYear();
    var G = date.getDay();
    var H;
    var I = false;
    
    $("#month").text(months[month] + ' , ' + year);
    if (typeof B !== 'undefined') {
        $(".day input:checkbox").attr("disabled", B)
    }
    
    let list;
    $.ajax({type: 'GET',
        async:false,
        url: `${window.location.origin}/hrpro/extension/getHariLibur/${month+1}/${year}`,
        cache: false,
        success: function(data) {
            data= JSON.parse(data);
            list = data;
        },error:function(err){
            
        }
    })

    $("#calendar .day").each(function(F) {
        $(this).removeClass("today");
        if (G + date.getDate() - 1 == F) {
           
            I = (data[date.toDateString()]) ? true : false;
            $(this).html(date.getDate());
            H = checkBox.clone().attr("title", date.toDateString()).prependTo(this);
            $(this).css('color',"black");
            if (I)
                H.click();
            if (date.toDateString() == today.toDateString()){
                $(this).addClass("today");
            }
            var idxhari = list.filter(function (item) {
              return new Date(item.tglharilibur).getDate()==date.getDate()
            });
            // console.log(idxhari);

            if(date.getDay()==0){
                $(H).attr('disabled','');
                $(this).css('color',"red");
            }
            if(idxhari.length){
                $(H).attr('title',idxhari[0].keterangan)
                $(H).attr('disabled','');
                $(this).css('color',"red");
            }
            if (date < today && tglLewat==true){
                $(H).attr('disabled','');
            } 
            if(B===true){
                 $(H).attr('disabled','');
            }
            // console.log(month +',' + year);
           //  var idxhari = list.map(function(item) {
           //    return new Date(item.tglharilibur).getDate()==date.getDate()
           // })
           
            
            date.setDate(date.getDate() + 1)
        } else {
            $(this).html("")
        }
    });

    date.setMonth(month);
    date.setFullYear(year);
    $(".day input:checkbox").on('change',function(){
        $("#done").click();
    })
    $(".day input:checkbox").unbind("click").click(function() {
        var J = $(this).attr("title");
        
        if (this.checked) {
            data[J] = true;
        } else {
            delete data[J]
        }
        return true
    });
    
}
// $("#calendar input:checkbox").on('change',function(){
    
// })
function selection() {
    var A = new Object();
    A['choosed'] = tlhpilih;
    A['count'] = jlhpilih;
    return A
}
function gkdisabled(A) {
    isdisabled = A
}
