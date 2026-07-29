@extends('layouts.app')

@section('title', 'Pratonton Data')

@section('page-title', 'Pratonton Data Excel')

@section('content')

    <div class="report-card">

        <h4 class="section-title">

            Pratonton Rekod

        </h4>

        <div class="table-responsive-custom">

            <table class="table-modern">

                @foreach ($preview as $row)
                    <tr>

                        @foreach ($row as $cell)
                            <td>

                                {{ $cell }}

                            </td>
                        @endforeach

                    </tr>
                @endforeach

            </table>

        </div>

    </div>

@endsection
