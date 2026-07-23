
$('.toastsDefaultSuccess').click(function() {

});



function openToastSuccess(color,title,body) {

    $(document).Toasts('create', {
        class: color,
        title: title,
        // subtitle: 'Subtitle',
        body: body
    })
}

function updateStatus( status, message) {

    statusChangeConnection = (oldStatusConnection != status)
    console.log(statusChangeConnection);
    let nows = new Date();
    if (statusChangeConnection) {
        if (status == 'error') {
            openToastSuccess('bg-danger','Koneksi Putus',nows)
            oldStatusConnection = status
            statusChangeConnection = false
        }else if(status == 'connected' ){
            openToastSuccess('bg-success','Koneksi Hidup',nows)
            oldStatusConnection = status
            statusChangeConnection = false
        }
    }

}

function monitorServer(url) {

    function connect() {
        const eventSource = new EventSource(url);

        eventSource.onopen = () => {
            updateStatus('connected', 'Connected');
        };

        eventSource.onmessage = (event) => {
            // console.log(`Message from server :`, event.data);
        };

        eventSource.onerror = () => {
            updateStatus('error', 'Error');
            eventSource.close();

            // Reconnect after 5 seconds
            setTimeout(() => {
                updateStatus('reconnecting', 'Reconnecting...');
                connect();
            }, 5000);
        };
    }

    connect();
}

