# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions
from markupsafe import Markup
import uuid


class ssw_task_inputs(models.Model):
    _name = 'ssw.proc.tasks.inputs'
    _description = 'Task Input'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name                          = fields.Char(string='Title')
    
    uuid                          = fields.Char(string='Import Reference', required=True, readonly=True, copy=False, default=lambda self: str(uuid.uuid4()), index=True)
    task_id                       = fields.Many2one('ssw.proc.tasks', string='task', required=True, tracking=True)
    company_id                    = fields.Many2one('res.company', string='Company', related='task_id.assigned_to_user.company_id')
    input_template_id             = fields.Many2one('ssw.proc.templates.inputs', string='Input template id', tracking=True)
    show_in_other_tasks           = fields.Many2many('ssw.proc.tasks', string='Show in other tasks', tracking=True)

    type                          = fields.Char(string='Type', required=True, tracking=True)

    result_value                  = fields.Char(string='Value', compute='_compute_value', store=True)

    value_char                    = fields.Char(string='Value', default="", tracking=True)
    value_int                     = fields.Integer(string='Value', default=0, tracking=True)
    value_date                    = fields.Date(string='Date', tracking=True)
    value_datetime                = fields.Datetime(string='Date', tracking=True)
    value_boolean                 = fields.Boolean(string='Value', default=False, tracking=True)
    value_image                   = fields.Image(string='Image')
    value_select                  = fields.Many2one('ssw.proc.tasks.inputs.subs', string='Select', tracking=True)

    reference_model_name          = fields.Char(string='Reference Model', tracking=True)
    value_model_id                = fields.Many2oneReference(
        string='Record',
        model_field='reference_model_name',
    )
    allow_multiple_model_values   = fields.Boolean(string='Allow multiple records', default=False, tracking=True)
    value_model_ids               = fields.One2many(
        'ssw.proc.tasks.inputs.model.values',
        'input_id',
        string='Records',
    )
    product_line_ids              = fields.One2many(
        'ssw.product.line',
        'ssw_task_input_id',
        string='Product Lines',
    )
    is_required                   = fields.Boolean(string='Is Required', default=True, tracking=True)
    deleted                       = fields.Boolean(string='Is Deleted', default=False, tracking=True)

    def _format_change(self, field_label, old_val, new_val):
        """Format a single field change as an HTML snippet."""
        if old_val and new_val:
            return (
                '<div style="margin: 4px 0;">'
                '<b>%s</b>: '
                '<span style="color: #dc3545; text-decoration: line-through;">%s</span>'
                ' → '
                '<span style="color: #28a745;">%s</span>'
                '</div>'
            ) % (field_label, old_val, new_val)
        elif new_val:
            return (
                '<div style="margin: 4px 0;">'
                '<b>%s</b>: '
                '<span style="color: #28a745;">%s</span>'
                '</div>'
            ) % (field_label, new_val)
        elif old_val:
            return (
                '<div style="margin: 4px 0;">'
                '<b>%s</b>: '
                '<span style="color: #dc3545; text-decoration: line-through;">%s</span>'
                ' → <em>(cleared)</em>'
                '</div>'
            ) % (field_label, old_val)
        return ''

    def _wrap_changes(self, parts):
        """Wrap change parts in a styled container and return Markup."""
        if not parts:
            return None
        body = (
            '<div style="padding: 8px; border-left: 3px solid #007bff; margin: 8px 0;">'
            '<div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Field Updated</div>'
            '%s'
            '</div>'
        ) % ''.join(parts)
        return Markup(body)

    @api.depends(
        'type', 'value_char', 'value_int', 'value_date', 'value_datetime',
        'value_boolean', 'value_image', 'value_select',
        'value_model_id', 'value_model_ids.value_model_id',
        'product_line_ids.product_id', 'product_line_ids.description',
        'product_line_ids.quantity', 'product_line_ids.product_uom_id',
        'reference_model_name', 'allow_multiple_model_values',
    )
    @api.onchange(
        'type', 'value_char', 'value_int', 'value_date', 'value_datetime',
        'value_boolean', 'value_image', 'value_select',
    )
    def _compute_value(self):
        for record in self:
            if record.type == 'char':
                record.result_value = record.value_char
            elif record.type == 'int':
                record.result_value = str(record.value_int)
            elif record.type == 'date':
                record.result_value = str(record.value_date)
            elif record.type == 'datetime':
                record.result_value = str(record.value_datetime)
            elif record.type == 'boolean':
                if record.value_boolean:
                    record.result_value = "Yes"
                else:
                    record.result_value = "No"
            elif record.type == 'select':
                record.result_value = str(record.value_select.name)
            elif record.type == 'image' and record.value_image:
                record.result_value = "Image 🖼️"
            elif record.type == 'model':
                if record.allow_multiple_model_values and record.reference_model_name == 'ssw.product.line':
                    record.result_value = record._get_product_lines_display()
                elif record.allow_multiple_model_values:
                    record.result_value = record._get_model_values_display()
                elif record.reference_model_name and record.value_model_id:
                    record.result_value = record._get_model_value_display(record.value_model_id)
                else:
                    record.result_value = ''
            else:
                record.result_value = ''

            if record.result_value == "False":
                record.result_value = ''

    def _message_track(self, fields_iter, initial_values_dict):
        tracked_values = super()._message_track(fields_iter, initial_values_dict)

        for record in self:
            values = initial_values_dict[record.id]
            parts = []

            if values['value_char'] != record.value_char:
                parts.append(record._format_change(
                    record.name, values['value_char'], record.value_char
                ))

            if values['value_int'] != record.value_int:
                parts.append(record._format_change(
                    record.name, str(values['value_int']) if values['value_int'] else '', str(record.value_int)
                ))

            if values['value_date'] != record.value_date:
                parts.append(record._format_change(
                    record.name, str(values['value_date']) if values['value_date'] else '', str(record.value_date) if record.value_date else ''
                ))

            if values['value_datetime'] != record.value_datetime:
                parts.append(record._format_change(
                    record.name, str(values['value_datetime']) if values['value_datetime'] else '', str(record.value_datetime) if record.value_datetime else ''
                ))

            if values['value_boolean'] != record.value_boolean:
                old_bool = "Yes" if values['value_boolean'] else "No"
                new_bool = "Yes" if record.value_boolean else "No"
                parts.append(record._format_change(record.name, old_bool, new_bool))

            if values['value_select'] != record.value_select:
                old_select = str(values['value_select']['name']) if values['value_select'] else ''
                new_select = record.value_select.name if record.value_select else ''
                parts.append(record._format_change(record.name, old_select, new_select))

            body = record._wrap_changes(parts)
            if body:
                task_model = self.env['ssw.proc.tasks'].browse([record.task_id.id])
                task_model.message_post(body=body)

        return tracked_values

    def _get_model_value_display(self, value_model_id):
        self.ensure_one()
        if not self.reference_model_name or not value_model_id:
            return ''
        try:
            related = self.env[self.reference_model_name].browse(value_model_id)
            return related.display_name if related.exists() else ''
        except Exception:
            return ''

    def _get_model_values_display(self):
        self.ensure_one()
        names = []
        for value in self.value_model_ids:
            display_name = self._get_model_value_display(value.value_model_id)
            if display_name:
                names.append(display_name)
        return ', '.join(names)

    def _get_product_lines_display(self):
        self.ensure_one()
        return str(len(self.product_line_ids))+' items'

    def write(self, values):
        # Capture old value_model_id before write for manual tracking
        old_model_values = {}
        if 'value_model_id' in values or 'value_model_ids' in values or 'product_line_ids' in values:
            for record in self:
                old_model_values[record.id] = {
                    'value_model_id': record.value_model_id,
                    'value_model_ids_display': record._get_model_values_display(),
                    'product_line_ids_display': record._get_product_lines_display(),
                    'reference_model_name': record.reference_model_name,
                    'name': record.name,
                    'task_id': record.task_id.id,
                }

        if self.task_id.state == "draft":
            result = super().write(values)

        elif self.task_id.state == "pending":
            vals = values
            if not isinstance(vals, list):
                vals = [vals]

            for payload in vals:
                if self.type in ['image', 'select', 'label']:
                    payload['result_value'] = ' '
                not_allowed_to_modify_fields = ['type', 'reference_model_name']
                for field_not_modifiable in not_allowed_to_modify_fields:
                    if field_not_modifiable in payload:
                        raise exceptions.ValidationError("You are not allowed to modify a closed or completed field")
            result = super().write(values)
        else:
            raise exceptions.ValidationError("You are not allowed to modify a closed or completed field")

        # Manual tracking for value_model_id after super().write()
        for record in self:
            if record.id in old_model_values:
                old_data = old_model_values[record.id]
                if old_data['value_model_id'] != record.value_model_id:
                    old_name = ''
                    new_name = ''
                    model_name = record.reference_model_name or old_data['reference_model_name']

                    if old_data['value_model_id'] and model_name:
                        try:
                            old_rec = record.env[model_name].browse(old_data['value_model_id'])
                            old_name = old_rec.display_name if old_rec.exists() else str(old_data['value_model_id'])
                        except Exception:
                            old_name = str(old_data['value_model_id'])

                    if record.value_model_id and model_name:
                        try:
                            new_rec = record.env[model_name].browse(record.value_model_id)
                            new_name = new_rec.display_name if new_rec.exists() else str(record.value_model_id)
                        except Exception:
                            new_name = str(record.value_model_id)

                    change = record._format_change(record.name, old_name, new_name)
                    if change:
                        body = record._wrap_changes([change])
                        if body:
                            task_model = self.env['ssw.proc.tasks'].browse([record.task_id.id])
                            task_model.message_post(body=body)

                if 'value_model_ids_display' in old_data:
                    new_values_display = record._get_model_values_display()
                    if old_data['value_model_ids_display'] != new_values_display:
                        change = record._format_change(record.name, old_data['value_model_ids_display'], new_values_display)
                        if change:
                            body = record._wrap_changes([change])
                            if body:
                                task_model = self.env['ssw.proc.tasks'].browse([record.task_id.id])
                                task_model.message_post(body=body)

                if 'product_line_ids_display' in old_data:
                    new_values_display = record._get_product_lines_display()
                    if old_data['product_line_ids_display'] != new_values_display:
                        change = record._format_change(record.name, old_data['product_line_ids_display'], new_values_display)
                        if change:
                            body = record._wrap_changes([change])
                            if body:
                                task_model = self.env['ssw.proc.tasks'].browse([record.task_id.id])
                                task_model.message_post(body=body)


        self.task_id.updateContext()
        self._handle_extra_modules()
        return result

    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
    def _handle_extra_modules(self):
        if self.reference_model_name == "ssd.document":
            document_ids = self.value_model_ids.mapped('value_model_id') if self.allow_multiple_model_values else [self.value_model_id]
            documents = self.env['ssd.document'].sudo().search([('id', 'in', document_ids)])
            if documents:
                users_to_add = []
                for user in self.task_id.users_can_view:
                    user = self.env['res.users'].sudo().search([('partner_id', '=', user.id)])
                    if user:
                        users_to_add.append((4, user.id))
                documents.write({
                    "allowed_user_ids": users_to_add
                })


class ssw_task_inputs_model_values(models.Model):
    _name = 'ssw.proc.tasks.inputs.model.values'
    _description = 'Task Input Model Value'

    input_id = fields.Many2one('ssw.proc.tasks.inputs', string='Task Input', required=True, ondelete='cascade')
    reference_model_name = fields.Char(
        string='Reference Model',
        default=lambda self: self.env.context.get('default_reference_model_name'),
        readonly=True,
    )
    value_model_id = fields.Many2oneReference(
        string='Record',
        model_field='reference_model_name',
        required=True,
    )

    @api.model_create_multi
    def create(self, vals_list):
        for vals in vals_list:
            if vals.get('input_id'):
                input_record = self.env['ssw.proc.tasks.inputs'].browse(vals['input_id'])
                vals['reference_model_name'] = input_record.reference_model_name
        return super().create(vals_list)

    def write(self, vals):
        if 'input_id' in vals:
            vals = dict(vals)
            input_record = self.env['ssw.proc.tasks.inputs'].browse(vals['input_id'])
            vals['reference_model_name'] = input_record.reference_model_name
        elif 'reference_model_name' in vals:
            vals = dict(vals)
            vals.pop('reference_model_name')
        return super().write(vals)
