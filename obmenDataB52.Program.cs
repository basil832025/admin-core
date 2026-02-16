using FirebirdSql.Data.FirebirdClient;
using System.Globalization;
using System.Text.RegularExpressions;

var builder = WebApplication.CreateBuilder(args);

var iniPath = Path.Combine(builder.Environment.ContentRootPath, "appsettings.ini");
if (!File.Exists(iniPath))
{
    throw new FileNotFoundException("Missing appsettings.ini", iniPath);
}

var ini = IniReader.Parse(iniPath);
var dbSettings = DbSettings.FromIni(ini);
var sqlStore = SqlCommandStore.FromIni(ini);

builder.Services.AddSingleton(dbSettings);
builder.Services.AddSingleton(sqlStore);
builder.Services.AddSingleton<SqlCommandExecutor>();

var app = builder.Build();

app.MapGet("/", () => "OK");

app.MapPost("/sql/{name}", async (string name, SqlRequest request, SqlCommandExecutor executor, CancellationToken ct) =>
{
    var result = await executor.ExecuteAsync(name, request.Parameters, ct);
    return Results.Ok(result);
});

app.Run();

record SqlRequest(Dictionary<string, object>? Parameters);

sealed class DbSettings
{
    public required string Host { get; init; }
    public string? Port { get; init; }
    public string? Name { get; init; }
    public required string User { get; init; }
    public required string Password { get; init; }
    public string? Charset { get; init; }

    public string BuildConnectionString()
    {
        var parts = new List<string>
        {
            "User=" + User,
            "Password=" + Password
        };

        if (!string.IsNullOrWhiteSpace(Charset))
        {
            parts.Add("Charset=" + Charset);
        }

        if (!string.IsNullOrWhiteSpace(Name))
        {
            parts.Add("DataSource=" + Host);
            parts.Add("Database=" + Name);
            if (!string.IsNullOrWhiteSpace(Port))
            {
                parts.Add("Port=" + Port);
            }
        }
        else
        {
            parts.Add("Database=" + Host);
        }

        return string.Join(';', parts);
    }

    public static DbSettings FromIni(Dictionary<string, Dictionary<string, string>> ini)
    {
        if (!ini.TryGetValue("Database", out var db))
        {
            throw new InvalidOperationException("[Database] section is required in appsettings.ini");
        }

        return new DbSettings
        {
            Host = db.GetValueOrDefault("host") ?? string.Empty,
            Port = db.GetValueOrDefault("port"),
            Name = db.GetValueOrDefault("name"),
            User = db.GetValueOrDefault("user") ?? string.Empty,
            Password = db.GetValueOrDefault("password") ?? string.Empty,
            Charset = db.GetValueOrDefault("charset")
        };
    }
}

sealed class SqlCommandStore
{
    private readonly Dictionary<string, string> _commands;

    private SqlCommandStore(Dictionary<string, string> commands)
    {
        _commands = commands;
    }

    public string Get(string name)
    {
        if (!_commands.TryGetValue(name, out var sql))
        {
            throw new KeyNotFoundException($"SQL command '{name}' not found");
        }
        return sql;
    }

    public static SqlCommandStore FromIni(Dictionary<string, Dictionary<string, string>> ini)
    {
        if (!ini.TryGetValue("SqlCommands", out var commands))
        {
            throw new InvalidOperationException("[SqlCommands] section is required in appsettings.ini");
        }

        return new SqlCommandStore(new Dictionary<string, string>(commands, StringComparer.OrdinalIgnoreCase));
    }
}

sealed class SqlCommandExecutor
{
    private static readonly Regex ParamRegex = new(@":([A-Za-z_][A-Za-z0-9_]*)", RegexOptions.Compiled);
    private readonly DbSettings _db;
    private readonly SqlCommandStore _store;

    public SqlCommandExecutor(DbSettings db, SqlCommandStore store)
    {
        _db = db;
        _store = store;
    }

    public async Task<object> ExecuteAsync(string name, Dictionary<string, object>? parameters, CancellationToken ct)
    {
        var sql = _store.Get(name);
        var normalizedSql = NormalizeParameters(sql);
        var isQuery = IsQuery(normalizedSql);

        await using var connection = new FbConnection(_db.BuildConnectionString());
        await connection.OpenAsync(ct);

        await using var command = new FbCommand(normalizedSql, connection);
        AddParameters(command, parameters);

        if (isQuery)
        {
            var rows = new List<Dictionary<string, object?>>();
            await using var reader = await command.ExecuteReaderAsync(ct);
            while (await reader.ReadAsync(ct))
            {
                var row = new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase);
                for (var i = 0; i < reader.FieldCount; i++)
                {
                    row[reader.GetName(i)] = reader.IsDBNull(i) ? null : reader.GetValue(i);
                }
                rows.Add(row);
            }
            return new { rows };
        }

        var affected = await command.ExecuteNonQueryAsync(ct);
        return new { affected };
    }

    private static string NormalizeParameters(string sql)
    {
        return ParamRegex.Replace(sql, match => "@" + match.Groups[1].Value);
    }

    private static bool IsQuery(string sql)
    {
        var trimmed = sql.TrimStart();
        return trimmed.StartsWith("select", StringComparison.OrdinalIgnoreCase)
               || trimmed.StartsWith("with", StringComparison.OrdinalIgnoreCase);
    }

    private static void AddParameters(FbCommand command, Dictionary<string, object>? parameters)
    {
        if (parameters == null || parameters.Count == 0)
        {
            return;
        }

        foreach (var (key, value) in parameters)
        {
            var name = key.Trim();
            if (name.StartsWith(":", StringComparison.Ordinal))
            {
                name = name[1..];
            }
            else if (name.StartsWith("@", StringComparison.Ordinal))
            {
                name = name[1..];
            }

            var param = new FbParameter("@" + name, ConvertJsonValue(value));
            command.Parameters.Add(param);
        }

    }

    private static object? ConvertJsonValue(object value)
    {
        if (value is System.Text.Json.JsonElement element)
        {
            return element.ValueKind switch
            {
                System.Text.Json.JsonValueKind.Number => element.TryGetInt64(out var l)
                    ? l
                    : element.TryGetDecimal(out var d)
                        ? d
                        : element.GetDouble(),
                System.Text.Json.JsonValueKind.String => element.GetString(),
                System.Text.Json.JsonValueKind.True => true,
                System.Text.Json.JsonValueKind.False => false,
                System.Text.Json.JsonValueKind.Null => null,
                _ => element.GetRawText()
            };
        }

        if (value is string s && DateTime.TryParse(s, CultureInfo.InvariantCulture, DateTimeStyles.None, out var dt))
        {
            return dt;
        }

        return value;
    }
}

static class IniReader
{
    public static Dictionary<string, Dictionary<string, string>> Parse(string path)
    {
        var result = new Dictionary<string, Dictionary<string, string>>(StringComparer.OrdinalIgnoreCase);
        Dictionary<string, string>? current = null;

        foreach (var rawLine in File.ReadAllLines(path))
        {
            var line = rawLine.Trim();
            if (line.Length == 0 || line.StartsWith(';') || line.StartsWith('#'))
            {
                continue;
            }

            if (line.StartsWith('[') && line.EndsWith(']'))
            {
                var section = line[1..^1].Trim();
                current = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
                result[section] = current;
                continue;
            }

            var separatorIndex = line.IndexOf('=');
            if (separatorIndex <= 0 || current == null)
            {
                continue;
            }

            var key = line[..separatorIndex].Trim();
            var value = line[(separatorIndex + 1)..].Trim();
            current[key] = value;
        }

        return result;
    }
}
