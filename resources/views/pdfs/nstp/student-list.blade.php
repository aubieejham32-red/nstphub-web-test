<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <title>
        {{ $title }}
    </title>

    @include('pdfs.nstp._styles')

</head>


<body>

    @include('pdfs.nstp._header')

    @include('pdfs.nstp._footer')


    <h1 class="document-title">
        {{ $title }}
    </h1>


    <div class="document-subtitle">

        NSTP Component:

        <strong>
            {{ $component ?: '-' }}
        </strong>

        &nbsp;·&nbsp;

        Total Students:

        <strong>
            {{ $students->count() }}
        </strong>

    </div>


    @if ($students->isEmpty())

        <div class="no-data">

            No enrolled students are available
            for this component.

        </div>

    @else

        <table class="data-table">

            <thead>

                <tr>

                    <th style="width:5%">
                        No.
                    </th>

                    <th style="width:12%">
                        Student ID
                    </th>

                    <th style="width:27%">
                        Full Name
                    </th>

                    <th style="width:15%">
                        Course
                    </th>

                    <th style="width:10%">
                        Year
                    </th>

                    <th style="width:10%">
                        Section
                    </th>

                    <th style="width:9%">
                        Component
                    </th>

                    <th style="width:12%">
                        NSTP Status
                    </th>

                </tr>

            </thead>


            <tbody>

                @foreach ($students as $index => $student)

                    <tr>

                        <td class="center">
                            {{ $index + 1 }}
                        </td>


                        <td class="center">
                            {{
                                $student->student_id_number
                                ??
                                $student->id
                                ??
                                '-'
                            }}
                        </td>


                        <td>
                            {{
                                $student->full_name
                                ??
                                '-'
                            }}
                        </td>


                        <td>
                            {{
                                $student->course
                                ?:
                                '-'
                            }}
                        </td>


                        <td class="center">
                            {{
                                $student->year_level
                                ?:
                                '-'
                            }}
                        </td>


                        <td class="center">
                            {{
                                $student->section
                                ?:
                                '-'
                            }}
                        </td>


                        <td class="center">
                            {{
                                !empty($student->component)
                                    ? strtoupper((string) $student->component)
                                    : '-'
                            }}
                        </td>


                        <td class="center">
                            {{
                                $student->nstp_status
                                ?:
                                'ACTIVE'
                            }}
                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    @endif

</body>

</html>