<div class="container {{ $class }}">
    {{-- <div class="row">
        <div class="col-xs-12 mb-30">
            <div id="interval-switch" class="interval-switch">
                <label class="ct-switch">
                    <input type="checkbox">
                    <span class="ct-slider round"></span>
                </label>
                <span>Yearly</span>
                <span class="badge bg-warning">20% discount</span>
            </div>
        </div>
    </div> --}}
    <div class="row">
        @foreach ($plans as $key => $plan)
            <div class="col-lg-4 col-md-6 col-sm-12 mb-30">
                @include('includes.pricing', $plan)
            </div>
        @endforeach
    </div>
</div>
{{-- <script>
    let year = false
    $("#interval-switch input").change(() => {
        $(".year").toggle(!year);
        $(".month").toggle(year);
        $(".plan-interval").val(!year ? 'year' : 'month');
        year = !year
    });
</script> --}}
