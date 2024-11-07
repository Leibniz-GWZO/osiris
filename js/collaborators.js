$(document).ready(function () {
    document.getElementById('ror-file').addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        Papa.parse(file, {
            header: true,
            complete: function (results) {
                console.log(results);
                // transform keys to lowercase
                var data = results.data.map(function (row) {
                    var newRow = {};
                    for (var key in row) {
                        newRow[key.toLowerCase()] = row[key];
                    }
                    return newRow;
                });
                console.log(data);

                var ror_rex = new RegExp('(.*ror.org/)?(0[a-z|0-9]{6}[0-9]{2})');

                for (var i = 0; i < data.length; i++) {
                    var row = data[i];
                    // check if ror is valid with regex
                    if (row.ror && ror_rex.test(row.ror)) {
                        addCollaboratorROR(row.ror, msg = false)
                        continue;
                    }
                    var values = {};
                    values.name = row.name;
                    values.role = row.role;
                    values.type = row.type;
                    values.location = row.location;
                    values.country = row.country;
                    values.lat = row.lat ?? row.latitude;
                    values.lng = row.lng ?? row.longitude;

                    addCollabRow(values);
                }

            }
        });
    });
});

function addCollaboratorROR(ror, msg = true) {
    if (!ror) {
        toastError('Please provide a ROR ID')
        return
    }
    var url = 'https://api.ror.org/organizations/' + ror.trim()
    $.ajax({
        type: "GET",
        url: url,

        success: function (response) {
            console.log(response);
            if (response.errors) {
                toastError(', '.join(response.errors))
                return
            }
            addCollaborator(response)
            $('#collaborators-ror-id').val('')
            if (msg)
                toastSuccess(lang('Collaborator added', 'Kooperationspartner hinzugefügt'))
        },
        error: function (response) {
            var errors = response.responseJSON.errors
            if (errors) {
                toastError(errors.join(', '))
            } else {
                toastError(response.responseText)
            }
            $('.loader').removeClass('show')
        }
    })
}

function getCollaborators(name) {
    console.log(name);
    const SUGGEST = $('#collaborators-suggest')
    SUGGEST.empty()
    var url = 'https://api.ror.org/organizations'
    var data = {
        affiliation: name
    }
    $.ajax({
        type: "GET",
        data: data,
        dataType: "json",

        url: url,
        success: function (response) {
            console.log(response);
            response.items.forEach(j => {
                var o = j.organization

                var row = $('<tr>')
                var button = $('<button class="btn" title="select">')
                button.html('<i class="ph ph-check text-success"></i>')
                button.on('click', function () {
                    addCollaborator(o);
                })

                var data = $('<td>')
                data.append(`<h5 class="m-0">${o.name}</h5>`)
                if (j.chosen) {
                    data.addClass('text-success')
                    button.addClass('success')
                        .attr('data-toggle', 'tooltip')
                        .attr('data-title', 'Best Result by ROR')

                }
                data.append(`<span class="float-right text-muted">${o.types[0]}</span>`)
                data.append(`<span class="text-muted">${o.addresses[0].city}, ${o.country.country_name}</span>`)

                row.append($('<td class="w-50">').append(button))

                row.append(data)

                SUGGEST.append(row)
            })
        },
        error: function (response) {
            toastError(response.responseText)
            $('.loader').removeClass('show')
        }
    })
}

function addCollabRow(data = {}) {
    let table = $('#collaborators')
    var i = table.find('tr').length
    console.log(i);

    let id = 'collab-' + i;
    var tr = `<tr id="${id}">
        <td>
            <input name="values[name][]" type="text" class="form-control" required value="${data.name ?? ''}">
        </td>
        <td>
            <select name="values[role][]" type="text" class="form-control " required>
                <option value="partner">Partner</option>
                <option value="coordinator">Coordinator</option>
            </select>
        </td>
        <td>
            <select name="values[type][]" type="text" class="form-control" required>
                <option value="Education">Education</option>
                <option value="Healthcare">Healthcare</option>
                <option value="Company">Company</option>
                <option value="Archive">Archive</option>
                <option value="Nonprofit">Nonprofit</option>
                <option value="Government">Government</option>
                <option value="Facility">Facility</option>
                <option value="Other">Other</option>
            </select>
        </td>
        <td class="hidden">
            <input name="values[ror][]" type="text" class="form-control" value="${data.ror ?? ''}">
        </td>
        <td>
            <input name="values[location][]" type="text" class="form-control" value="${data.location ?? ''}">
        </td>
        <td>
            <input name="values[country][]" type="text" maxlength="2" class="form-control w-50" required value="${data.country ?? ''}">
        </td>
        <td>
            <input name="values[lat][]" type="text" class="form-control w-100" value="${data.lat ?? ''}">
        </td>
        <td>
            <input name="values[lng][]" type="text" class="form-control w-100" value="${data.lng ?? ''}">
        </td>
        <td>
            <button class="btn danger my-10" type="button" onclick="$(this).closest('tr').remove()"><i class="ph ph-trash"></i></button>
        </td>
    </tr>`;

    table.append(tr)
    // console.log($('#'+id).find('select'));
    console.log($('#' + id).find('select option[value="' + data.type + '"]'));
    $('#' + id).find('select option[value="' + data.type + '"]').attr('selected', true)
}

function addCollaborator(data = {}) {
    let address = data.addresses[0]
    // let city = address.city
    let lat = address.lat
    let lng = address.lng

    let ror = data.id
    let name = data.name
    let type = data.types[0]
    // Education, Healthcare, Company, Archive, Nonprofit, Government, Facility, Other
    let country = data.country.country_code
    let location = address.city + ", " + data.country.country_name

    addCollabRow({
        name: name,
        ror: ror,
        location: location,
        country: country,
        lat: lat,
        lng: lng,
        type: type
    });

    $('#collaborators-suggest').empty()
    $('#collaborators-search').val('')
}